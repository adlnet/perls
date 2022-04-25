<?php

namespace Drupal\perls_recommendation;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flag\FlagServiceInterface;
use Drupal\perls_recommendation\Entity\UserRecommendationStatus;
use Drupal\user\UserInterface;

/**
 * A helper service for recommendation engine.
 */
class RecommendService {
  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The configuration factory.
   *
   * @var Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The queue factory.
   *
   * @var Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The Recommendation Engine Plugin manager.
   *
   * @var Drupal\perls_recommendation\RecommendationEnginePluginManager
   */
  protected $recommendationManger;

  /**
   * The Flag Service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CategoryLayout object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The Current User.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param Drupal\perls_recommendation\RecommendationEnginePluginManager $recommendation_manager
   *   The recommendation engine plugin manager.
   * @param Drupal\flag\FlagServiceInterface $flag_service
   *   The Flag service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The Entity Manager.
   */
  public function __construct(
      AccountInterface $current_user,
      ConfigFactory $config_factory,
      QueueFactory $queue_factory,
      RecommendationEnginePluginManager $recommendation_manager,
      FlagServiceInterface $flag_service,
      EntityTypeManagerInterface $entity_manager
    ) {
    $this->currentUser = $current_user;
    $this->config = $config_factory->get('perls_recommendation.settings');
    $this->queueFactory = $queue_factory;
    $this->recommendationManger = $recommendation_manager;
    $this->flagService = $flag_service;
    $this->entityTypeManager = $entity_manager;
  }

  /**
   * Queue or process an update to an entity.
   */
  public function updateEntity(EntityInterface $entity) {
    $this->processUpdateEntity($entity, $this->getUseQueueGraph());
  }

  /**
   * Delete an entity from the recommendation engine system.
   */
  public function deleteEntity(EntityInterface $entity) {
    $this->processUpdateEntity($entity, $this->getUseQueueGraph(), TRUE);
    // If user delete recommendation status entity.
    if ($entity->getEntityTypeId() === 'user') {
      $status_array = $this->entityTypeManager
        ->getStorage('user_recommendation_status')
        ->loadByProperties(
        [
          'user_id' => $entity->id(),
        ]
      );
      foreach ($status_array as $status) {
        $status->delete();
      }
    }
  }

  /**
   * Queue or get user recommendations.
   */
  public function getUserRecommendations(UserInterface $user) {
    if ($this->getUseQueueRecommend()) {
      $this->queueUserRecommendations($user);
    }
    else {
      $this->processUserRecommendations($user, TRUE);
    }
  }

  /**
   * Process recommendation queue.
   */
  public function processRecommendationQueue() {
    $status_array = $this->entityTypeManager
      ->getStorage('user_recommendation_status')
      ->loadByProperties(
        [
          'status' =>
          [
            UserRecommendationStatus::STATUS_QUEUED,
            UserRecommendationStatus::STATUS_PROCESSING,
            UserRecommendationStatus::STATUS_READY,
          ],
        ]
      );
    foreach ($status_array as $status) {
      if ($user = $status->user_id->entity) {
        $this->processUserRecommendations($user);
      }
      else {
        $status->delete();
      }
    }
  }

  /**
   * Queue all users to get new recommendations.
   */
  public function queueAllUserRecommendations() {
    $properties = ['status' => 1];
    $users = $this->entityTypeManager->getStorage('user')->loadByProperties($properties);
    foreach ($users as $user) {
      $this->queueUserRecommendations($user);
    }
  }

  /**
   * A user is being added to queue for recommendations.
   */
  public function queueUserRecommendations(UserInterface $user) {
    foreach ($this->getRecommendationEngines() as $recommendation_engine) {
      $recommendation_engine->queueUserForRecommendations($user);
    }
  }

  /**
   * Process get user recommendations.
   */
  public function processUserRecommendations(UserInterface $user, $now = FALSE) {
    // Check to see if all recommendation engines are ready.
    // We do not process recommendations unless all active recommendation
    // engines are ready.
    if (!$now) {
      foreach ($this->getRecommendationEngines() as $id => $recommendation_engine) {
        if (!$recommendation_engine->userRecommendationsReady($user)) {
          return FALSE;
        }
      }
    }
    $flag_id = $this->config->get('recommendation_flag_id') ?: 'recommendation';
    $flag = $this->flagService->getFlagById($flag_id);
    if (!$flag) {
      return;
    }
    $recommendations = [];
    foreach ($this->getRecommendationEngines() as $id => $recommendation_engine) {
      $recommendations_local = $recommendation_engine->getUserRecommendations(
        $user,
        $this->config->get($id . '_number_recommendations'),
        $now
      );
      // Remove old recommendations before adding new ones.
      $this->removeCurrentFlags($id, $user);
      // Merge these results with results of other plugins.
      // Using + operator here to maintain nids in keys. If a key appears in
      // on both sides the left hand item is used.
      $recommendations = $recommendations + $recommendations_local;
    }
    // Allow results to be altered.
    \Drupal::moduleHandler()->invokeAll('perls_recommendations_alter', [&$recommendations]);
    // Recommend new content.
    if (!empty($recommendations)) {
      foreach ($recommendations as $recommendation) {
        // We need to ensure that the node isn't already flagged.
        if ($flag_object = $this->flagService->getFlagging($flag, $recommendation->node, $user)) {
          // If from placeholder or filler views we update score and reason.
          // Keep manual flags in case admin set them on purpose.
          if ($flag_object->field_recommendation_plugin->value === 'placeholder') {
            $flag_object->set('field_recommendation_plugin', $recommendation->recommendationSource);
            $flag_object->set('field_recommendation_reason', $recommendation->recommendationReason);
            $flag_object->set('field_recommendation_score', $recommendation->weight);
            $flag_object->save();
          }
          continue;
        }
        $flag_object = $this->flagService->flag($flag, $recommendation->node, $user);
        $flag_object->set('field_recommendation_plugin', $recommendation->recommendationSource);
        $flag_object->set('field_recommendation_reason', $recommendation->recommendationReason);
        $flag_object->set('field_recommendation_score', $recommendation->weight);
        $flag_object->save();
      }
    }
    return TRUE;
  }

  /**
   * Process a entity update.
   */
  public function processUpdateEntity(EntityInterface $entity, $use_queue = FALSE, $delete_entity = FALSE) {
    foreach ($this->getRecommendationEngines() as $recommendation_engine) {
      if ($recommendation_engine->requiresUpdateFromEntity($entity)) {
        $recommendation_engine->updateEntity($entity, $use_queue, $delete_entity);
      }
    }
    if ($entity->getEntityTypeId() === 'user' && !$delete_entity) {
      $this->getUserRecommendations($entity);
    }
  }

  /**
   * Check to see if recommendation is connected.
   */
  public function checkStatus() {
    $status_array = [];
    $plugin_definitions = $this->recommendationManger->getDefinitions();
    foreach ($plugin_definitions as $id => $definition) {
      if ($this->config->get($id . '_enabled')) {
        $status_array[$id]['title'] = $definition['label'];
        // Load a version of the recommendation engine plugin.
        try {
          /** @var \Drupal\perls_recommendation\RecommendationEnginePluginInterface $recommendation_engine */
          $recommendation_engine = $this->recommendationManger
            ->createInstance($id);
        }
        catch (PluginException $e) {
          $status_array[$id]['status'] = FALSE;
          $status_array[$id]['description'] = $this->t('Failed to connect to plugin with error:  @message', ['@message' => $e->getMessage()]);
          continue;
        }
        $status = $recommendation_engine->getStatus();
        if (is_array($status)) {
          $status_array[$id]['status'] = $status['status'];
          $status_array[$id]['description'] = $status['description'];
        }
        else {
          $status_array[$id]['status'] = $status;
        }
      }

    }
    return $status_array;
  }

  /**
   * Is service set to process or queue graph requests.
   */
  public function getUseQueueGraph() {
    return ($this->config->get('use_queue_graph') == 1) ? TRUE : FALSE;
  }

  /**
   * Is service set to process or queue recommendations.
   */
  public function getUseQueueRecommend() {
    return ($this->config->get('use_queue_recommend') == 1) ? TRUE : FALSE;
  }

  /**
   * Get an array of active plugins.
   */
  protected function getRecommendationEngines() {
    $recommendation_engines = [];
    $plugin_definitions = $this->recommendationManger->getDefinitions();
    foreach ($plugin_definitions as $id => $definition) {
      if ($this->config->get($id . '_enabled') === 1) {
        // Load a version of the recommendation engine plugin.
        try {
          /** @var \Drupal\perls_recommendation\RecommendationEnginePluginInterface $recommendation_engine */
          $recommendation_engine = $this->recommendationManger
            ->createInstance($id);
        }
        catch (PluginException $e) {
          continue;
        }
        $recommendation_engines[$id] = $recommendation_engine;
      }
    }
    return $recommendation_engines;
  }

  /**
   * Delete current flags for a plugin id.
   */
  public function removeCurrentFlags($plugin_id, UserInterface $user = NULL) {
    $flag_id = $this->config->get('recommendation_flag_id') ?: 'recommendation';
    $flag = $this->flagService->getFlagById($flag_id);
    if (!$flag) {
      return;
    }
    // Properties to search for.
    $properties = [
      'flag_id' => $flag->id(),
      'field_recommendation_plugin' => $plugin_id,
    ];
    // If a user is given limit the search to that user.
    if ($user) {
      $properties['uid'] = $user->id();
    }
    $results = $this->entityTypeManager->getStorage('flagging')->loadByProperties($properties);

    foreach ($results as $result) {
      $result->delete();
    }
  }

  /**
   * Reset Recommendation graph.
   */
  public function resetGraph() {
    foreach ($this->getRecommendationEngines() as $recommendation_engine) {
      $recommendation_engine->resetGraph();
    }
    // Delete all status entries.
    $status_array = $this->entityTypeManager
      ->getStorage('user_recommendation_status')
      ->loadByProperties(
        [
          'status' =>
          [
            UserRecommendationStatus::STATUS_RETRIEVED,
            UserRecommendationStatus::STATUS_QUEUED,
            UserRecommendationStatus::STATUS_PROCESSING,
            UserRecommendationStatus::STATUS_READY,
          ],
        ]
      );
    foreach ($status_array as $status) {
      $status->delete();
    }
    return TRUE;
  }

  /**
   * Rebuild Graph from scratch.
   */
  public function rebuildGraph() {
    // We will implement this as a batch.
    $operations = [];
    foreach ($this->getRecommendationEngines() as $recommendation_engine) {
      // Queue all topics.
      $properties = ['vid' => 'category'];
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties($properties);
      foreach ($terms as $id => $term) {
        if ($recommendation_engine->requiresUpdateFromEntity($term)) {
          $operations[] = [
            [
              $recommendation_engine,
              'updateEntity',
            ],
            [
              $term,
              FALSE,
              FALSE,
            ],
          ];
        }
      }
      // Queue all users (including relationships from profile).
      $properties = ['status' => 1];
      $users = $this->entityTypeManager->getStorage('user')->loadByProperties($properties);
      foreach ($users as $id => $user) {
        if ($recommendation_engine->requiresUpdateFromEntity($user)) {
          $operations[] = [
            [
              $recommendation_engine,
              'updateEntity',
            ],
            [
              $user,
              FALSE,
              FALSE,
            ],
          ];
        }
      }
      // Queue all nodes.
      $properties = ['status' => 1];
      $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties($properties);
      foreach ($nodes as $id => $node) {
        if ($recommendation_engine->requiresUpdateFromEntity($node)) {
          $operations[] = [
            [
              $recommendation_engine,
              'updateEntity',
            ],
            [
              $node,
              FALSE,
              FALSE,
            ],
          ];
        }
      }
      // Queue all relationships from flags.
      $properties = [];
      $flags = $this->entityTypeManager->getStorage('flagging')->loadByProperties($properties);
      foreach ($flags as $id => $flag) {
        if ($recommendation_engine->requiresUpdateFromEntity($flag)) {
          $operations[] = [
            [
              $recommendation_engine,
              'updateEntity',
            ],
            [
              $flag,
              FALSE,
              FALSE,
            ],
          ];
        }
      }
    }
    $batch = [
      'title' => $this->t('Rebuilding graph ...'),
      'operations' => $operations,
      'init_message'     => $this->t('Getting ready to graph all items'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message'    => $this->t('An error occurred during processing'),
      'finished' => '',
    ];
    batch_set($batch);
  }

  /**
   * Sync items that have been updated locally since last sync.
   */
  public function syncRecommendationEngine() {
    foreach ($this->getRecommendationEngines() as $recommendation_engine) {
      $recommendation_engine->syncGraph(1000);
    }
  }

}
