<?php

namespace Drupal\recommender;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\recommender\Entity\RecommendationCandidate;
use Drupal\recommender\Entity\RecommendationHistory;
use Drupal\recommender\Entity\RecommendationPluginScore;
use Drupal\recommender\Entity\UserRecommendationStatus;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Class RecommendService.
 */
class RecommendationService implements RecommendationServiceInterface {
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
   * @var Drupal\recommender\RecommendationEnginePluginManager
   */
  protected $recommendationPluginManager;

  /**
   * The recommendation score combine plugin manager.
   *
   * @var \Drupal\recommender\RecommendationScoreCombinePluginManager
   */
  protected $recommendationScoreCombineManager;

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
   * The time interface.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * The logger Interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The database.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new CategoryLayout object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The Current User.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param Drupal\recommender\RecommendationEnginePluginManager $recommendation_manager
   *   The recommendation engine plugin manager.
   * @param \Drupal\recommender\RecommendationScoreCombinePluginManager $recommendation_score_combine_manager
   *   The plugin manager service for recommendation score combine.
   * @param Drupal\flag\FlagServiceInterface $flag_service
   *   The Flag service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The Entity Manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time interface.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher service.
   * @param \Psr\Log\LoggerInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
      AccountInterface $current_user,
      ConfigFactory $config_factory,
      QueueFactory $queue_factory,
      RecommendationEnginePluginManager $recommendation_manager,
      RecommendationScoreCombinePluginManager $recommendation_score_combine_manager,
      FlagServiceInterface $flag_service,
      EntityTypeManagerInterface $entity_manager,
      TimeInterface $time,
      AccountSwitcherInterface $account_switcher,
      LoggerInterface $logger_factory,
      Connection $database
    ) {
    $this->currentUser = $current_user;
    $this->config = $config_factory->get('recommender.settings');
    $this->queueFactory = $queue_factory;
    $this->recommendationPluginManager = $recommendation_manager;
    $this->recommendationScoreCombineManager = $recommendation_score_combine_manager;
    $this->flagService = $flag_service;
    $this->entityTypeManager = $entity_manager;
    $this->time = $time;
    $this->accountSwitcher = $account_switcher;
    $this->logger = $logger_factory;
    $this->database = $database;
  }

  /**
   * Process recommendation queue.
   */
  public function processRecommendationQueue($limit = -1) {
    $end = ($limit !== -1) ? time() + $limit : -1;
    $required_timeout = strtotime('-' . $this->getRecommendationTimeout());

    $query = $this->entityTypeManager
      ->getStorage('sl_user_recommendation_status')
      ->getQuery();

    $group = $query->orConditionGroup()
      ->condition('recommendations_updated', $required_timeout, '<')
      ->condition('recommendations_updated', NULL, 'IS NULL');

    $status_ids = $query->condition('status',
      [
        UserRecommendationStatus::STATUS_QUEUED,
        UserRecommendationStatus::STATUS_GENERATE_CANDIDATE,
        UserRecommendationStatus::STATUS_ALTER_CANDIDATE,
        UserRecommendationStatus::STATUS_SCORE_CANDIDATE,
        UserRecommendationStatus::STATUS_COMBINE_SCORE,
      ], 'IN'
      )
      ->condition($group)
      ->sort('recommendations_priority', 'DESC')
      ->range(0, 100)
      ->execute();
    if (empty($status_ids)) {
      return;
    }

    foreach ($status_ids as $status_id) {
      $status = UserRecommendationStatus::load($status_id);
      if ($user = $status->user_id->entity) {
        $this->processUserRecommendations($user, $status);
      }
      else {
        $status->delete();
      }
      // If a timelimit has been set respect it.
      if ($end !== -1 && time() > $end) {
        break;
      }
    }
  }

  /**
   * Mark as recommendations as stale .
   * */
  public function markRecommendationStatusAsStale() {
    // Stale recommendations only affect systems where recommendations
    // are generated on login.
    $freshness_cutoff = $this->getRecommendationFreshnesslimit();
    if (!($this->shouldBuildOnUserLogin() || $this->shouldBuildWithAjax()) || $freshness_cutoff === 'never') {
      return;
    }
    $status_ids = $this->entityTypeManager
      ->getStorage('sl_user_recommendation_status')
      ->getQuery()
      ->condition('status',
        UserRecommendationStatus::STATUS_READY
      )
      ->condition('recommendations_updated', strtotime('-' . $freshness_cutoff), '<')
      ->execute();
    if (empty($status_ids)) {
      return;
    }
    foreach ($status_ids as $id) {
      $status = UserRecommendationStatus::load($id);
      $status->setStatus(UserRecommendationStatus::STATUS_STALE);
      $status->save();
    }
  }

  /**
   * Build all user recommendations.
   */
  public function buildAllUserRecommendations($build_now = FALSE) {

    $batch = [
      'title' => $this->t('Queue User for Recommendation ...'),
      'operations' => [
          ['\Drupal\recommender\RecommendationService::queueAllUsersBatch',
          [$build_now],
          ],
      ],
      'progress_message' => '',
      'init_message'     => $this->t('Preparing to queue users for recommendations'),
      'error_message'    => $this->t('An error occurred while adding users to recommendation queue'),
      'batch_redirect' => 'admin/config/system/recommendation_engine/configure',
    ];

    batch_set($batch);
  }

  /**
   * A Batch process to queue all users for recommendations.
   */
  public static function queueAllUsersBatch($build_now, &$context) {
    $storage = \Drupal::entityTypeManager()->getStorage('user');
    if (!isset($context['sandbox']['progress'])) {
      $total_users = $storage
        ->getQuery()
        ->condition('status', 1)
        ->count()
        ->execute();
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = $total_users;
    }

    $users = $storage
      ->getQuery()
      ->condition('status', 1)
      ->range($context['sandbox']['progress'], 5)
      ->execute();

    $context['sandbox']['progress'] += count($users);

    foreach ($users as $user_id) {
      $user = $storage->load($user_id);
      // If you use dependency inject here it breaks batch.
      $status = \Drupal::service('recommender.recommendation_service')->getUserStatus($user);
      $status->setStatus(UserRecommendationStatus::STATUS_QUEUED);
      $status->setPriority(0);
      $status->save();
      if ($build_now) {
        \Drupal::service('recommender.recommendation_service')->processUserRecommendations($user, $status);
      }
    }
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildUserRecommendations(AccountInterface $user, $priority = 0, $now = FALSE) {
    if (!$user) {
      return FALSE;
    }
    $status = $this->getUserStatus($user);
    $status->setStatus(UserRecommendationStatus::STATUS_QUEUED);
    $status->setPriority($priority);
    $status->save();
    if (!$this->getUseCronRecommend() || $now) {
      $this->processUserRecommendations($user, $status);
    }
  }

  /**
   * Check to see if recommendation is connected.
   */
  public function checkStatus() {
    $status_array = [];
    $plugin_definitions = $this->recommendationPluginManager->getDefinitions();
    foreach ($plugin_definitions as $id => $definition) {
      if ($this->config->get($id . '_enabled')) {
        $status_array[$id]['title'] = $definition['label'];
        // Load a version of the recommendation engine plugin.
        try {
          /** @var \Drupal\recommender\RecommendationEnginePluginInterface $recommendation_engine */
          $recommendation_engine = $this->recommendationPluginManager
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
   * Is service set to run the rerank system on page load.
   */
  public function rerankOnLoad() {
    return ($this->config->get('rerank_on_load') == 1) ? TRUE : FALSE;
  }

  /**
   * Is service set to process or queue recommendations.
   */
  public function getUseCronRecommend() {
    return ($this->config->get('cron_recommendations') == 1) ? TRUE : FALSE;
  }

  /**
   * Is the recommendation service in debug mode.
   */
  public function getDebugEnabled() {
    return ($this->config->get('enable_debug') == 1) ? TRUE : FALSE;
  }

  /**
   * Check if recommendations are build on user registeration.
   */
  public function shouldBuildOnRegistration() {
    return ($this->config->get('build_recommendations_on_registration') !== NULL) ? $this->config->get('build_recommendations_on_registration') : TRUE;
  }

  /**
   * Check if recommendations are build on user update.
   */
  public function shouldBuildOnUserUpdate() {
    return ($this->config->get('build_recommendations_on_user_update') !== NULL) ? $this->config->get('build_recommendations_on_user_update') : TRUE;
  }

  /**
   * Should build with ajax.
   */
  public function shouldBuildWithAjax() {
    return ($this->config->get('build_recommendations_with_ajax') !== NULL) ? $this->config->get('build_recommendations_with_ajax') : FALSE;
  }

  /**
   * Recommendation ajax view name.
   */
  public function getRecommendationAjaxView() {
    return ($this->config->get('recommendation_ajax_view') !== NULL) ? $this->config->get('recommendation_ajax_view') : 'vault_recommendations';
  }

  /**
   * Recommendation ajax view display id.
   */
  public function getRecommendationAjaxViewDisplayId() {
    return ($this->config->get('recommendation_ajax_view_display_id') !== NULL) ? $this->config->get('recommendation_ajax_view_display_id') : 'block_1';
  }

  /**
   * Should build on user login.
   */
  public function shouldBuildOnUserLogin() {
    return ($this->config->get('build_recommendations_on_login') !== NULL) ? $this->config->get('build_recommendations_on_login') : FALSE;
  }

  /**
   * Get the recommendation history timespan.
   */
  public function recommendationHistoryStorageLimit() {
    return ($this->config->get('store_history') !== NULL) ? $this->config->get('store_history') : 'forever';
  }

  /**
   * Get the minimum time between recommendations.
   */
  public function getRecommendationTimeout() {
    return $this->config->get('recommendation_timeout') ?: '0 seconds';
  }

  /**
   * Get the time after which a recommendation is stale.
   */
  public function getRecommendationFreshnesslimit() {
    return $this->config->get('stale_recommendations') ?: '4 weeks';
  }

  /**
   * Reset Recommendation engine.
   */
  public function resetUserRecommendations(AccountInterface $user = NULL) {
    // Delete all recommendation candidates.
    $query = $this->database->delete('sl_recommendation_candidate');
    $query->condition('status', [
      RecommendationCandidate::STATUS_QUEUED,
      RecommendationCandidate::STATUS_PROCESSING,
      RecommendationCandidate::STATUS_READY,
    ], 'IN');

    if ($user) {
      $query->condition('user_id', $user->id());
    }
    $query->execute();

    // Delete all recommendation candidate scores.
    $query = $this->database->delete('sl_recommendation_plugin_score');
    $query->condition('status', [
      RecommendationPluginScore::STATUS_PROCESSING,
      RecommendationPluginScore::STATUS_READY,
    ], 'IN');

    if ($user) {
      $query->condition('user_id', $user->id());
    }
    $query->execute();
    // Delete all status entries.
    $query = $this->database->delete('sl_user_recommendation_status');
    $query->condition('status', [
      UserRecommendationStatus::STATUS_QUEUED,
      UserRecommendationStatus::STATUS_GENERATE_CANDIDATE,
      UserRecommendationStatus::STATUS_ALTER_CANDIDATE,
      UserRecommendationStatus::STATUS_SCORE_CANDIDATE,
      UserRecommendationStatus::STATUS_COMBINE_SCORE,
      UserRecommendationStatus::STATUS_READY,
      UserRecommendationStatus::STATUS_STALE,
    ], 'IN');

    if ($user) {
      $query->condition('user_id', $user->id());
    }
    $query->execute();
    return TRUE;
  }

  /**
   * Delete all user recommendations in the system.
   */
  public function deleteUserRecommendations() {
    $flag_id = $this->config->get('recommendation_flag_id') ?: 'recommendation';
    $batch = [
      'title' => $this->t('Delete all User Recommendations ...'),
      'operations' => [
          ['\Drupal\recommender\RecommendationService::removeAllRecommendationsBatch',
          [$flag_id],
          ],
      ],
      'progress_message' => '',
      'init_message'     => $this->t('Preparing to delete recommendations'),
      'error_message'    => $this->t('An error occurred while deleting recommendations'),
      'batch_redirect' => 'admin/config/system/recommendation_engine/configure',
    ];

    batch_set($batch);
  }

  /**
   * A Batch process to queue all users for recommendations.
   */
  public static function removeAllRecommendationsBatch($flag_id, &$context) {
    $storage = \Drupal::entityTypeManager()->getStorage('flagging');
    if (!isset($context['sandbox']['progress'])) {
      $total_users = $storage
        ->getQuery()
        ->condition('flag_id', $flag_id)
        ->count()
        ->execute();
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = $total_users;
    }

    $flag_ids = $storage
      ->getQuery()
      ->condition('flag_id', $flag_id)
      ->range(0, 50)
      ->execute();

    $context['sandbox']['progress'] += count($flag_ids);
    if ($flag_ids) {
      $flags = $storage->loadMultiple($flag_ids);
      foreach ($flags as $flag) {
        $flag->delete();
      }
    }

    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Get an array of all available recommendation engine plugins.
   *
   * @param string $stage
   *   You can optionally supply a stage which will limit the
   *   array to plugins for that stage.
   * @param bool $return_only_active
   *   Limit returned plugins to only active plugins.
   *
   * @return array
   *   An array of recommendation plugins for the given stage
   *   or all if stage is null.
   */
  public function getRecommendationEnginePlugins($stage = NULL, $return_only_active = FALSE) {
    // If stage it set it should be valid.
    if ($stage && !in_array($stage, array_keys($this->recommendationPluginManager->getRecommendationStages()))) {
      throw new RecommendationEngineException(
        'Invalid plugin stage requested, The requested "'
        . $stage .
        '" is not a valid recommendation engine stage.');
    }
    $recommendation_plugins = [];
    $recommenation_plugin_weights = [];
    foreach ($this->recommendationPluginManager->getDefinitions() as $name => $plugin_definition) {
      if (class_exists($plugin_definition['class'])) {
        /** @var \Drupal\recommender\RecommendationEnginePluginInterface $recommendation_engine */
        $recommendation_engine_plugin = $this->recommendationPluginManager
          ->createInstance($name);
        // If stage is set plugin must support this stage.
        if ($stage && !$recommendation_engine_plugin->supportsStage($stage)) {
          continue;
        }
        // Skip disabled plugins if requested.
        if ($return_only_active && !$this->config->get($name . '_enabled')) {
          continue;
        }

        $recommendation_plugins[$name] = $recommendation_engine_plugin;
        $recommenation_plugin_weights[$name] = $recommendation_engine_plugin->getWeight($stage);
      }
      else {
        $this->logger->warning('Recommendation Plugin %id specifies a non-existing class %class.', [
          '%id' => $name,
          '%class' => $plugin_definition['class'],
        ]);
      }
    }
    asort($recommenation_plugin_weights);
    $ordered_plugins = [];
    foreach ($recommenation_plugin_weights as $id => $weight) {
      $ordered_plugins[$id] = $recommendation_plugins[$id];
    }
    return $ordered_plugins;
  }

  /**
   * Get an array of all score combine plugins.
   *
   * @return array
   *   Returns an associative array with $plugin_id => $plugin for each
   *   available plugin.
   */
  public function getScoreCombinePlugins() {
    $score_combine_plugins = [];
    foreach ($this->recommendationScoreCombineManager->getDefinitions() as $name => $plugin_definition) {
      if (class_exists($plugin_definition['class'])) {
        /** @var \Drupal\recommender\RecommendationScoreCombinePluginInterface $score_combine_plugin */
        $score_combine_plugin = $this->recommendationScoreCombineManager->createInstance($name);
        $score_combine_plugins[$name] = $score_combine_plugin;
      }
      else {
        $this->logger->warning('Recommendation Score Combine Plugin %id specifies a non-existing class %class.', [
          '%id' => $name,
          '%class' => $plugin_definition['class'],
        ]);
      }
    }

    return $score_combine_plugins;
  }

  /**
   * Get an array list of valid recommendation plugin stages.
   */
  public function getRecommendationStages() {
    return $this->recommendationPluginManager->getRecommendationStages();
  }

  /**
   * Remove scores from associated plugin.
   */
  public function removePluginScores($plugin) {
    if (!$plugin) {
      return;
    }

    $score_properties = [
      'plugin_id' => $plugin,
    ];
    $status_array = $this->entityTypeManager
      ->getStorage('recommendation_plugin_score')
      ->loadByProperties($score_properties);
    foreach ($status_array as $status) {
      $status->delete();
    }
  }

  /**
   * Get or create user recommendation status entity for a given user.
   *
   * @param Drupal\Core\Session\AccountInterface $user
   *   The user of interest.
   */
  public function getUserStatus(AccountInterface $user) {
    if (!$user) {
      return;
    }
    $status = $this->entityTypeManager
      ->getStorage('sl_user_recommendation_status')
      ->loadByProperties(['user_id' => $user->id()]);
    if (!empty($status)) {
      // Return saved entity.
      return reset($status);
    }
    return UserRecommendationStatus::create(['user_id' => $user->id()]);
  }

  /**
   * {@inheritdoc}
   *
   * Get or Create a candidate entity for a user node pair.
   */
  public function getCandidateEntity(AccountInterface $user, NodeInterface $candidate) {
    if (!$user) {
      return;
    }
    $entity = $this->entityTypeManager
      ->getStorage('recommendation_candidate')
      ->loadByProperties(
        [
          'user_id' => $user->id(),
          'nid' => $candidate->nid->value,
        ]
      );
    if (!empty($entity)) {
      // Return saved entity.
      return reset($entity);
    }
    $entity = RecommendationCandidate::create(
      [
        'user_id' => $user->id(),
        'status' => RecommendationCandidate::STATUS_PROCESSING,
        'nid' => $candidate->nid->value,
      ]
    );
    $entity->save();
    return $entity;
  }

  /**
   * Store a record of all items recommended to user.
   */
  protected function storeRecommendationHistory(RecommendationCandidate $candidate) {
    if (in_array($this->recommendationHistoryStorageLimit(), ['never', '0'])) {
      return;
    }
    $entity = RecommendationHistory::create(
      [
        'user_id' => $candidate->user_id->getValue(),
        'nid' => $candidate->nid->getValue(),
      ]
    );
    $entity->setCombinedScore($candidate->getCombinedScore());
    $entity->setReason($candidate->getReason());
    $entity->save();
  }

  /**
   * Remove old recommendation history items.
   */
  public function cleanUpRecommendationHistory($limit = -1) {
    $end = ($limit !== -1) ? time() + $limit : -1;
    $storage_limit = $this->recommendationHistoryStorageLimit();
    if ($storage_limit === 'forever') {
      // No need to truncate table.
      return;
    }
    $delete_before = strtotime('-' . $storage_limit);
    if (!$delete_before) {
      return;
    }
    $items_to_delete = $this->entityTypeManager
      ->getStorage('recommendation_history')
      ->getQuery()
      ->range(0, 5000)
      ->condition('changed',
      $delete_before, '<'
      )
      ->execute();
    if (empty($items_to_delete)) {
      return;
    }

    foreach ($items_to_delete as $item) {
      $history_item = RecommendationHistory::load($item);
      $history_item->delete();
      // If a timelimit has been set respect it.
      if ($end !== -1 && time() > $end) {
        break;
      }
    }
  }

  /**
   * Get active score combine plugin.
   */
  protected function getActiveScoreCombinePlugin() {
    $plugins = $this->getScoreCombinePlugins();
    $active_plugin = $this->config->get('score_combine_plugin');
    return (isset($plugins[$active_plugin])) ? $plugins[$active_plugin] : reset($plugins);
  }

  /**
   * Process get user recommendations.
   *
   * Recommendations are calculated in 4 phases these are:
   * Generate Candidates - Create a list of possible nodes to recommend.
   * Alter Candidates - Remove unwanted items from list.
   * Score Candidates - Give a scores to each candidate
   * (Scores get combined after this).
   * Rerank candidates - allows alterations to final recommendation score.
   */
  protected function processUserRecommendations(AccountInterface $user, UserRecommendationStatus $status) {

    // Cron and admin can run recommendations for a given user.
    // To ensure correct access we need to use account Switcher.
    $account_switched = FALSE;
    if ($this->currentUser->id() !== $user->id()) {
      $this->accountSwitcher->switchTo($user);
      $account_switched = TRUE;
    }

    $debug = $this->getDebugEnabled();
    $start_time = $this->time->getCurrentMicroTime();

    $status->setStatus(UserRecommendationStatus::STATUS_GENERATE_CANDIDATE);
    $status->save();
    // Generate the candidates.
    $candidates = $this->generateCandidates($user);

    if ($debug) {
      $candidate_time = $this->time->getCurrentMicroTime();
      $candidate_count = count($candidates);
    }

    $status->setStatus(UserRecommendationStatus::STATUS_ALTER_CANDIDATE);
    $status->save();

    // Alter generated candidates.
    $candidates = $this->alterCandidates($candidates, $user);
    if ($debug) {
      $alter_candidate_time = $this->time->getCurrentMicroTime();
      $alter_candidate_count = count($candidates);
    }

    $status->setStatus(UserRecommendationStatus::STATUS_SCORE_CANDIDATE);
    $status->save();

    // Score the candidates.
    $this->scoreCandidates($candidates, $user);

    if ($debug) {
      $score_time = $this->time->getCurrentMicroTime();
    }

    $status->setStatus(UserRecommendationStatus::STATUS_COMBINE_SCORE);
    $status->save();

    // Combine the score into single value.
    $this->combineScore($candidates, $user);

    if ($debug) {
      $score_combine_time = $this->time->getCurrentMicroTime();
    }

    $status->setStatus(UserRecommendationStatus::STATUS_RERANK_CANDIDATE);
    $status->save();

    // Allow for results to be altered.
    $candidates = $this->rerankCandidates($candidates, $user);

    if ($debug) {
      $rerank_time = $this->time->getCurrentMicroTime();
    }

    // Flag content as recommendations.
    $number_recommendations = $this->flagRecommendations($candidates, $user);
    $completed_time = $this->time->getCurrentMicroTime();
    if ($debug) {
      $this->logger->debug('Generated %number_recommendations recommendations for user %user in %total_time s, <br/> Generated Candidates - %candidate_gen ss (%candidate_count items) <br/> Alter Candidates - %candidate_alter s (%alter_count items) <br/> Scoring - %score s <br/> Score combine - %score_combine s <br/> Rerank - %rerank s <br/> Recommend - %recommend s ', [
        '%number_recommendations' => $number_recommendations,
        '%user' => $user->getDisplayName(),
        '%total_time' => $completed_time - $start_time,
        '%candidate_gen' => $candidate_time - $start_time,
        '%candidate_count' => $candidate_count,
        '%candidate_alter' => $alter_candidate_time - $candidate_time,
        '%alter_count' => $alter_candidate_count,
        '%score' => $score_time - $alter_candidate_time,
        '%score_combine' => $score_combine_time - $score_time,
        '%rerank' => $rerank_time - $score_combine_time,
        '%recommend' => $completed_time - $rerank_time,
      ]);
    }

    // Update status of user recommendations.
    $status->setRetrieved($number_recommendations);
    $status->setUpdated($this->time->getCurrentTime());
    $status->setPriority(0);
    $status->setDuration($completed_time - $start_time);
    $status->setStatus(UserRecommendationStatus::STATUS_READY);
    $status->save();
    if ($account_switched) {
      $this->accountSwitcher->switchBack();
    }
    return TRUE;
  }

  /**
   * Generate candidates.
   */
  protected function generateCandidates(AccountInterface $user) {
    $candidate_entities = [];
    $debug = $this->getDebugEnabled();

    // Get a list of plugins enabled for candidate generation stage.
    $plugins = $this->getRecommendationEnginePlugins(RecommendationEnginePluginInterface::STAGE_GENERATE_CANDIDATES, TRUE);
    foreach ($plugins as $plugin) {
      if ($debug) {
        $start_time = $this->time->getCurrentMicroTime();
      }
      // For each plugin request candidates.
      $candidates = $plugin->generateCandidates($user);
      if ($debug) {
        $gen_time = $this->time->getCurrentMicroTime();
      }
      // Now create a candidate object for each node.
      foreach ($candidates as $candidate) {
        if (!isset($candidate_entities[$candidate->id()])) {
          $candidate_entity = $this->getCandidateEntity($user, $candidate);
          // Remove old score references from candidates since they get reused.
          $candidate_entity->scores = [];
          $candidate_entity->save();
          $candidate_entities[$candidate->id()] = $candidate_entity;
        }
      }
      if ($debug) {
        $end_time = $this->time->getCurrentMicroTime();
        $this->logger->debug('Generate Candidates - %plugin_id : Generate List %gen_time - Create entities %entity_time', [
          '%plugin_id' => $plugin->label(),
          '%gen_time' => $gen_time - $start_time,
          '%entity_time' => $end_time - $gen_time,
        ]);
      }
    }
    return $candidate_entities;
  }

  /**
   * Alter candidates.
   */
  protected function alterCandidates(array $candidates, AccountInterface $user) {
    $debug = $this->getDebugEnabled();
    // Get all plugins that alter the candidates list.
    $plugins = $this->getRecommendationEnginePlugins(RecommendationEnginePluginInterface::STAGE_ALTER_CANDIDATES, TRUE);
    foreach ($plugins as $plugin) {
      if ($debug) {
        $start_time = $this->time->getCurrentMicroTime();
      }
      // Each plugin that has registered gets to alter the list.
      $candidates = $plugin->alterCandidates($candidates, $user);
      if ($debug) {
        $end_time = $this->time->getCurrentMicroTime();
        $this->logger->debug('Alter Candidates - %plugin_id : %time', [
          '%plugin_id' => $plugin->label(),
          '%time' => $end_time - $start_time,
        ]);
      }
    }
    return $candidates;
  }

  /**
   * Score candidates.
   */
  protected function scoreCandidates(array $candidates, AccountInterface $user) {
    $debug = $this->getDebugEnabled();
    $plugins = $this->getRecommendationEnginePlugins(RecommendationEnginePluginInterface::STAGE_SCORE_CANDIDATES, TRUE);
    foreach ($plugins as $plugin) {
      if ($debug) {
        $start_time = $this->time->getCurrentMicroTime();
      }
      // Allow each plugin to score the recommendation candidates.
      $plugin->scoreCandidates($candidates, $user);
      if ($debug) {
        $end_time = $this->time->getCurrentMicroTime();
        $this->logger->debug('Score Candidates - %plugin_id : %time', [
          '%plugin_id' => $plugin->label(),
          '%time' => $end_time - $start_time,
        ]);
      }
    }
  }

  /**
   * Combine the recommendation scores from each plugin into a single score.
   */
  protected function combineScore(array $candidates, AccountInterface $user) {
    $debug = $this->getDebugEnabled();
    if ($debug) {
      $start_time = $this->time->getCurrentMicroTime();
    }
    $language = $user->getPreferredLangcode();
    foreach ($candidates as $nid => $candidate) {
      $score_service = $this->getActiveScoreCombinePlugin();
      $score = $score_service->getScore($candidate);
      $candidate->setCombinedScore($score);
      $candidate->setReason($score_service->getReason($candidate, $language));
      $candidate->save();
    }
    if ($debug) {
      $end_time = $this->time->getCurrentMicroTime();
      $this->logger->debug('Combine Score - %user : %time', [
        '%user' => $user->getDisplayName(),
        '%time' => $end_time - $start_time,
      ]);
    }
  }

  /**
   * Rerank Candidates.
   *
   * Allow plugins to alter the final recommendation candidates
   * with combined score.
   */
  protected function rerankCandidates(array $candidates, AccountInterface $user) {
    $plugins = $this->getRecommendationEnginePlugins(RecommendationEnginePluginInterface::STAGE_RERANK_CANDIDATES, TRUE);
    foreach ($plugins as $plugin) {
      $candidates = $plugin->rerankCandidates($candidates, $user);
    }
    return $candidates;
  }

  /**
   * Flag Recommendations.
   *
   * This method turns recommendation candidates into recommendation flags.
   */
  protected function flagRecommendations(array $candidates, AccountInterface $user) {
    $recommendation_count = 0;
    $flag_id = $this->config->get('recommendation_flag_id') ?: 'recommendation';
    $flag = $this->flagService->getFlagById($flag_id);
    if (!$flag) {
      return 0;
    }
    // Clear up old recommendations.
    $this->removeCurrentFlags('recommendation_engine', $user);
    // Allow results to be altered.
    \Drupal::moduleHandler()->invokeAll('recommender_recommendations_alter', [&$candidates]);
    // Recommend new content.
    if (!empty($candidates)) {
      foreach ($candidates as $nid => $candidate) {
        $node = $candidate->nid->entity;
        $score = $candidate->getCombinedScore();
        // If the score is zero it really shouldn't be recommended.
        if ($score === 0) {
          continue;
        }
        // Need to check that this node type can be recommended.
        if (!in_array($node->getType(), $flag->getApplicableBundles())) {
          continue;
        }
        // We need to ensure that the node isn't already flagged.
        // We do this here to avoid overwriting manual recommendations.
        if ($flag_object = $this->flagService->getFlagging($flag, $node, $user)) {
          continue;
        }
        $flag_object = $this->flagService->flag($flag, $node, $user);
        $flag_object->set('field_recommendation_plugin', 'recommendation_engine');
        $flag_object->set('field_recommendation_reason', $candidate->getReason());
        $flag_object->set('field_recommendation_score', $score);
        $flag_object->save();
        $this->storeRecommendationHistory($candidate);
        $recommendation_count++;
      }
    }
    return $recommendation_count;

  }

  /**
   * Delete current flags for a plugin id.
   */
  protected function removeCurrentFlags($plugin_id = NULL, AccountInterface $user = NULL) {
    $flag_id = $this->config->get('recommendation_flag_id') ?: 'recommendation';
    $flag = $this->flagService->getFlagById($flag_id);
    if (!$flag) {
      return;
    }
    // Properties to search for.
    $properties = [
      'flag_id' => $flag->id(),
    ];

    // If a plugin id is given limit the search to that plugin.
    if ($plugin_id) {
      $properties['field_recommendation_plugin'] = $plugin_id;
    }

    // If a user is given limit the search to that user.
    if ($user) {
      $properties['uid'] = $user->id();
    }
    $results = $this->entityTypeManager->getStorage('flagging')->loadByProperties($properties);

    foreach ($results as $result) {
      $result->delete();
    }
    return TRUE;
  }

  /**
   * Returns if a given user has recommendations.
   */
  public function hasRecommendations(UserInterface $user) {
    // We could update this to return false if recommendations are stale.
    $flag_id = $this->config->get('recommendation_flag_id') ?: 'recommendation';
    $flag = $this->flagService->getFlagById($flag_id);
    if (!$flag) {
      return;
    }
    $query = $this->entityTypeManager->getStorage('flagging')->getQuery();
    $query->condition('uid', $user->id())
      ->condition('flag_id', $flag->id());
    // We should also check status to see if recommendations are stale.
    $status = $this->getUserStatus($user);

    return ($query->count()->execute() > 0 && $status->getStatus() !== UserRecommendationStatus::STATUS_STALE) ? TRUE : FALSE;
  }

}
