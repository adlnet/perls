<?php

namespace Drupal\perls_recommendation\Plugin\RecommendationEngine;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\flag\FlaggingInterface;
use Drupal\node\NodeInterface;
use Drupal\perls_recommendation\Entity\UserRecommendationStatus;
use Drupal\perls_recommendation\Recommendation\Recommendation;
use Drupal\perls_recommendation\RemoteRecommendationEnginePluginBase;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Random Recommendation engine plugin.
 *
 * @RecommendationEngine(
 *   id = "perls_recommendation_engine",
 *   label = @Translation("Perls Recommendation Engine"),
 *   description = @Translation("Uses a remote connection to the legacy Perls recommendation engine.")
 * )
 */
class PerlsRecommendationEnginePlugin extends RemoteRecommendationEnginePluginBase {

  /**
   * The entity types used by the recommendation engine.
   */
  private const ACCEPTED_ENTITY_TYPES = [
    'node' => [
      'course',
      'flash_card',
      'learn_article',
      'learn_file',
      'learn_link',
      'learn_package',
      'quiz',
      'tip_card',
    ],
    'user' => [
      'user',
    ],
    'taxonomy_term' => [
      'category',
    ],
    'flagging' => [
      'bookmark',
      'completed',
      'seen',
      'topic_completed',
    ],
  ];

  /**
   * Learning phase Subtopic Suffixes .
   */
  private const PHASE_SUFFIXES =
  [
    'Explore',
    'Study',
    'Sharpen',
  ];

  /**
   * Recommendation endpoints.
   */
  const RE_DELETE_OBJECTS_ENDPOINT = '/corpus/delete/';
  const RE_DELETE_OBJECT_ENDPOINT = '/corpus/delete/{id}';
  const RE_USER_ENDPOINT = '/corpus/user/{username}';
  const RE_LO_ENDPOINT = '/corpus/lo/';
  const RE_TOPIC_ENDPOINT = '/corpus/group/';
  const RE_COURSE_ENDPOINT = '/corpus/group/';
  const RE_RELATION_ENDPOINT = '/corpus/relation/';
  const RE_LIST_ENDPOINT = '/corpus/list/';
  const RE_LIST_VIEW_ENDPOINT = '/corpus/listview/';
  const RE_USER_QUEUE = '/recommend/{username}/queue';
  const RE_USER_RECOMMEND_STATUS = '/recommend/{username}/status';
  const RE_USER_RECOMMEND = '/recommend/{username}';
  const RE_USERSTATUS_RELATION = '/user/{username}/userStatus/{type}/{vertexId}';
  const RE_USER_RELATION = '/user/{username}/{edgeType}/{vertexId}';
  const RE_TOPIC_COMPETENCY = '/goal/{username}/{vertexId}';

  /**
   * Relationship ID seperator.
   */
  const ID_SEPARATOR = '-';

  /**
   * The configuration settings.
   *
   * @var Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The current user.
   *
   * @var Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Holds all necessary data for proper unserialization.
   *
   * @var array
   */
  protected $serializationData;

  /**
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('Perls Recommendation Engine'),
      $container->get('http_client'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('database')
    );
  }

  /**
   * Constructor for Recommendation Engine.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerInterface $logger_factory,
    Client $http_client,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactory $config_factory,
    AccountInterface $current_user,
    Connection $database
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $http_client, $database, $entity_type_manager);
    $this->config = $config_factory->get('perls_recommendation.settings');
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserRecommendations(UserInterface $user, $count = 5, $now = FALSE) {
    $recommendations = [];
    $replacements = [
      '{username}' => $user->id(),
    ];
    $parameters = [
      'numRecs' => $count,
    ];
    if ($now) {
      $parameters['now'] = TRUE;
    }
    $endpoint = $this->generateEndpointUrl(self::RE_USER_RECOMMEND, $replacements, $parameters);
    $response = $this->makeRequest($endpoint);
    if ($response['status_code'] != 200 || empty($response['body'])) {
      return $recommendations;
    }
    if ($response['body']->numberOfRecommendations > 0) {
      foreach ($response['body']->recommendations as $type => $recommendation) {

        // Check against local item records.
        $entity_id = $recommendation->learningObject->description;
        $entity_id = explode(self::ITEM_RECORD_TYPE_SEPERATOR, $entity_id);
        // Load the entity.
        $node = $this->entityTypeManager->getStorage($entity_id[1])->load($entity_id[2]);
        $recommendations[$entity_id[2]] = new Recommendation(
          $entity_id[2],
          $node,
          $entity_id[1],
          $recommendation->recommendationReasons->EXPLORE[0]->valueProposition->strength,
          $recommendation->recommendationReasons->EXPLORE[0]->valueProposition->title,
          $this->pluginId
        );
      }
    }
    $status = $this->getOrCreateUserRecommendationStatus($user);
    $status->setStatus(UserRecommendationStatus::STATUS_RETRIEVED);
    $time = new DrupalDateTime($response['body']->timestamp);
    $status->setUpdated($time->getTimestamp());
    $status->setLastRecId($response['body']->cacheId);
    $status->setRetrieved($response['body']->numberOfRecommendations);
    $status->save();

    return $recommendations;
  }

  /**
   * {@inheritdoc}
   */
  public function queueUserForRecommendations(UserInterface $user) {
    $status = $this->getOrCreateUserRecommendationStatus($user);
    $status->setStatus(UserRecommendationStatus::STATUS_QUEUED);
    $status->save();
    $replacements = [
      '{username}' => $user->id(),
    ];
    $endpoint = $this->generateEndpointUrl(self::RE_USER_QUEUE, $replacements);
    $this->makeRequest($endpoint, self::POST);
  }

  /**
   * {@inheritdoc}
   */
  public function userRecommendationsReady(UserInterface $user) {
    $replacements = [
      '{username}' => $user->id(),
    ];
    $endpoint = $this->generateEndpointUrl(self::RE_USER_RECOMMEND_STATUS, $replacements);
    $response = $this->makeRequest($endpoint, self::GET);
    if ($response['status_code'] != 200 || empty($response['body'])) {
      return FALSE;
    }
    $status = $this->getOrCreateUserRecommendationStatus($user);
    if ($response['body']->inProgress) {
      $status->setStatus(UserRecommendationStatus::STATUS_PROCESSING);
      $status->save();
      return FALSE;
    }
    if ($response['body']->lastRecId === $status->getLastRecId()) {
      // No change to recommendation so wait for new calculation.
      return FALSE;
    }
    $status->setStatus(UserRecommendationStatus::STATUS_READY);
    $status->save();
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    // 1 - Check that plugin is configured correctly.
    if (
      empty($this->config->get($this->pluginId . '_url')) ||
      empty($this->config->get($this->pluginId . '_port'))) {
      $url = Url::fromRoute('perls_recommendation.admin_settings_form');
      // Log connectivity issue.
      $this->logger
        ->warning($this->getPluginDefinition()['label'] . ' endpoint is not configured');
      return [
        'status' => FALSE,
        'description' => $this->t('Recommendation Engine not configured. Please ensure you have added values to configuration for <a href=":url">url and port</a> .', [':url' => $url->toString()]),
      ];
    }
    // 2 - Check that Recommendation engine is reachable.
    $endpoint = $this->generateEndpointUrl(self::RE_LIST_ENDPOINT);
    try {
      $response = $this->httpClient->get($endpoint);
    }
    catch (\Exception $e) {
      $this->logger
        ->error($this->getPluginDefinition()['label'] . ' failed status check with error: ' . $e->getMessage() . '.');
      return [
        'status' => FALSE,
        'description' => $this->t('Recommendation engine endpoint return this Message: @message <br/> Recommendation engine server: @server:@port',
          [
            '@message' => $e->getMessage(),
            '@server' => $this->config->get($this->pluginId . '_url'),
            '@port' => $this->config->get($this->pluginId . '_port'),
          ]
        ),
      ];
    }
    // 3 - Check for content.
    $response_body = json_decode($response->getBody());
    if ($response_body === '') {
      $this->logger
        ->error($this->pluginDefinition->label . ' responded with no content. This is unusual and should be investigated.');
      return [
        'status' => FALSE,
        'description' => $this->t('Recommendation engine returned an empty response with code @code @text',
          [
            '@code' => $response->getStatusCode(),
            '@text' => $response_body,
          ]
        ),
      ];
    }
    // 4 - Check Status.
    if ($response->getStatusCode() !== 200) {
      $this->logger
        ->error($this->pluginDefinition->label . ' responded with an non 200 status code.');
      return [
        'status' => FALSE,
        'description' => $this->t('Recommendation engine returned with code @code  - @text',
          [
            '@code' => $response->getStatusCode(),
            '@text' => $response_body,
          ]
        ),
      ];
    }

    return [
      'status' => TRUE,
      'description' => $this->t('Ready to use.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function updateEntity(EntityInterface $entity, $use_queue = FALSE, $delete_entity = FALSE) {
    $entity_type = $entity->getEntityTypeId() . ':' . $entity->bundle();
    $remote_id = NULL;
    $remote_type = NULL;
    $status = NULL;
    if ($item_record = $this->getItemRecordByEntity($entity)) {
      $remote_id = $item_record[0]->remote_id;
      $remote_type = $item_record[0]->remote_type;
      $status = $item_record[0]->status;
    }

    if (!$this->requiresUpdateFromEntity($entity)) {
      return FALSE;
    }
    // If we are using queue we stop here and set status as needs sync.
    if ($use_queue) {
      $this->createOrUpdateItemRecord($entity, self::STATUS_NEEDS_SYNC, $remote_id, $remote_type);
      return TRUE;
    }

    // All entities can be deleted in the same way so handle delete first.
    if ($delete_entity) {
      if ($item_record) {
        if ($item_record[0]->remote_type === 'Topic') {
          $this->deleteSubtopics($item_record[0]->item_id);
        }
        // If we delete a LO we need to update subtopic prerequisites.
        if ($item_record[0]->remote_type === 'LearningObject') {
          $this->updateSubTopicPrerequisiteRelationships($item_record[0]->remote_id, TRUE);
        }
        return $this->deleteEntityByItemRecord($item_record[0]);
      }
      // There is no record so we can return true.
      return TRUE;
    }

    switch ($entity_type) {
      case 'user:user':
        $response = $this->updateUser($entity, $remote_id, $remote_type);
        break;

      case 'node:learn_article':
      case 'node:learn_file':
      case 'node:learn_link':
      case 'node:learn_package':
        $response = $this->updateLearningObject($entity, $remote_id, $remote_type);
        break;

      case 'node:quiz':
      case 'node:tip_card':
      case 'node:flash_card':
        $item_record_l = (!empty($item_record)) ? $item_record[0] : NULL;
        $response = $this->updateNestableLearningObject($entity, $item_record_l);
        break;

      case 'node:course':
        $response = $this->updateCourse($entity, $remote_id, $remote_type);
        break;

      case 'taxonomy_term:category':
        $response = $this->updateTopic($entity, $remote_id, $remote_type);
        break;

      case 'flagging:seen':
      case 'flagging:completed':
      case 'flagging:bookmark':
      case 'flagging:topic_completed':
        $response = $this->updateFlagging($entity, $remote_id, $remote_type);
        break;

    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function deleteEntityByItemRecord(object $item_record) {
    // Attempt to delete item from Recommendation Engine.
    if (!isset($item_record->remote_id)) {
      // This item hasn't been saved remotely so delete locally.
      return parent::deleteEntityByItemRecord($item_record);
    }
    $replacements = [
      '{id}' => urlencode($item_record->remote_id),
    ];
    $endpoint = $this->generateEndpointUrl(self::RE_DELETE_OBJECT_ENDPOINT, $replacements);
    $response = $this->makeRequest($endpoint, self::DELETE);
    if ($response['status_code'] == 200 || $response['status_code'] == 401) {
      // We need to clear relationship item records too.
      // Check for relationships where this item is source.
      $item_records_to_delete = $this->getExistingRelationships($item_record->remote_id, '.*', '.*');
      // Check for relationships where this item is sink.
      $item_records_to_delete += $this->getExistingRelationships('.*', $item_record->remote_id, '.*');
      // Delete relationship item records.
      foreach ($item_records_to_delete as $item_id => $remote_id) {
        $conditions =
        [
          'plugin_id' => $this->pluginId,
          'item_id' => $item_id,
          'remote_id' => $remote_id,
        ];
        $this->deleteItemRecords($conditions);
      }
      return parent::deleteEntityByItemRecord($item_record);
    }
    $item_record->status = self::STATUS_NEEDS_SYNC;
    $this->updateItemRecordStatus($item_record);
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresUpdateFromEntity(EntityInterface $entity) {
    // Check entity type.
    if (!in_array($entity->getEntityTypeId(), array_keys(self::ACCEPTED_ENTITY_TYPES))) {
      return FALSE;
    }
    // Check entity bundle.
    return in_array($entity->bundle(), self::ACCEPTED_ENTITY_TYPES[$entity->getEntityTypeId()]);
  }

  /**
   * {@inheritdoc}
   */
  public function resetGraph() {
    // When resetting the graph we can't assume that drupal is up to date.
    // Resetting the graph is what would happen when things break.
    // So instead we query the recommendation engine to get a list of nodes
    // and edges and use this list to null the graph.
    $endpoint = $this->generateEndpointUrl(self::RE_LIST_ENDPOINT);
    $response = $this->makeRequest($endpoint);
    if (!$response) {
      return FALSE;
    }
    // Parse the response to get ids from all the objects.
    $ids = [];
    foreach ($response['body'] as $type => $graphObjects) {
      foreach ($graphObjects as $object) {
        if ($object->id) {
          $ids[] = $object->id;
        }
      }
    }
    // Now run the delete query.
    $endpoint = $this->generateEndpointUrl(self::RE_DELETE_OBJECTS_ENDPOINT);
    $response = $this->makeRequest($endpoint, self::DELETE, $ids);
    if (!$response) {
      return FALSE;
    }
    $this->deleteItemRecords(['plugin_id' => $this->pluginId]);
    return TRUE;
  }

  /**
   * Generate a full endpoint url for the given operation.
   *
   * @param string $operation
   *   The path of the required current endpoint.
   * @param array $replacements
   *   Some paths have placeholders, give values for those here.
   * @param array $parameters
   *   If you want to add $parameters to endpoint give them here.
   *
   * @return string
   *   The url to use in the current request.
   */
  private function generateEndpointUrl($operation, array $replacements = [], array $parameters = []) {
    $endpoint_url = $this->config->get($this->pluginId . '_url');
    if (empty($endpoint_url)) {
      // If config isn't set don't go any further.
      return NULL;
    }
    // Add the port if set.
    if ($port = $this->config->get($this->pluginId . '_port')) {
      $endpoint_url .= ':' . $port;
    }
    // Add the operation.
    $endpoint_url .= $operation;
    // Add replacements if there are any.
    if (!empty($replacements)) {
      foreach ($replacements as $key => $value) {
        $endpoint_url = str_replace($key, $value, $endpoint_url);
      }
    }
    // Add parameters if there are any.
    if (!empty($parameters)) {
      $endpoint_url .= '?' . http_build_query($parameters);
    }
    return $endpoint_url;
  }

  /**
   * Sync a user entity with recommendation engine.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user that was updated.
   * @param string $remote_id
   *   The existing recommendation engine id for this user.
   * @param string $remote_type
   *   The existing object type on the recommendation engine.
   */
  protected function updateUser(UserInterface $user, $remote_id = NULL, $remote_type = NULL) {
    $replacements = [
      '{username}' => $user->id(),
    ];
    $endpoint_url = $this->generateEndpointUrl(self::RE_USER_ENDPOINT, $replacements);
    $response = $this->makeRequest($endpoint_url);
    if (!$response || $response['status_code'] != 200 || $response['body']->id == NULL) {
      // Failed to save it to recommendation engine so queue locally.
      $this->createOrUpdateItemRecord($user, self::STATUS_NEEDS_SYNC, $remote_id, $remote_type);
      return FALSE;
    }
    $existing_userStar = $this->getExistingRelationships($response['body']->id, '.*', 'userStar');
    if ($topic_list = $user->field_interests) {
      $topic_list = $topic_list->referencedEntities();
      foreach ($topic_list as $topic) {
        if ($item_record = $this->getItemRecordByEntity($topic, self::CREATE_IF_NEEDED)) {
          if (!$item_record[0]->remote_id) {
            // Referenced item hasn't been sync'd so has no id.
            // Set this node to need sync again in future and continue.
            $this->createOrUpdateItemRecord($user, self::STATUS_NEEDS_SYNC, $remote_id, 'PerlsUser');
            continue;
          }
          $this->addUserRelation($response['body']->id, $user->id(), $item_record[0]->remote_id, 'userStar');
          unset($existing_userStar[$this->getRelationshipsId($response['body']->id, $item_record[0]->remote_id, 'userStar')]);
          // We add userstars to all subtopics too.
          foreach (self::PHASE_SUFFIXES as $phase) {
            $subtopic_record = $this->getOrCreateSubTopic($item_record[0]->item_id, $phase, $item_record[0]->remote_id, $topic);
            if (is_array($subtopic_record) && isset($subtopic_record[0])) {
              $this->addUserRelation($response['body']->id, $user->id(), $subtopic_record[0]->remote_id, 'userStar');
              unset($existing_userStar[$this->getRelationshipsId($response['body']->id, $subtopic_record[0]->remote_id, 'userStar')]);
            }
          }
        }
      }
    }
    // Link to default topic to ensure you get recommendations.
    if ($item_record = $this->getDefaultTopic()) {
      if (!$item_record[0]->remote_id) {
        // Referenced item hasn't been sync'd so has no id.
        // Set this node to need sync again in future and continue.
        $this->createOrUpdateItemRecord($user, self::STATUS_NEEDS_SYNC, $remote_id, 'PerlsUser');
      }
      else {
        $this->addUserRelation($response['body']->id, $user->id(), $item_record[0]->remote_id, 'userStar');
        unset($existing_userStar[$this->getRelationshipsId($response['body']->id, $item_record[0]->remote_id, 'userStar')]);
      }
    }
    // Remove old userstars.
    $this->removeRelations($existing_userStar);
    $this->createOrUpdateItemRecord($user, self::STATUS_SYNCED, $response['body']->id, 'PerlsUser');
    return TRUE;
  }

  /**
   * Sync a Nestable Learning Object entity with recommendation engine.
   *
   * Learning objects like Quiz, Flash card and Tip card are nestable
   * in other learning objects. Their behavior changes based on if they
   * are contained in another learning object.
   *
   * If they learning object does not have a parent learning object it is
   * treated like a regular learning objects. If it does have a parent
   * it is removed from the graph and another process is used to suggest
   * that content.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that was updated.
   * @param object $item_record
   *   The existing item record for this Object.
   */
  protected function updateNestableLearningObject(NodeInterface $node, $item_record = NULL) {
    // Check if it is nested.
    if ($node->field_parent_content->isEmpty()) {
      // Treat learning Object as normal learning object.
      $remote_id = ($item_record !== NULL) ? $item_record->remote_id : NULL;
      $remote_type = ($item_record !== NULL) ? $item_record->remote_type : NULL;
      return $this->updateLearningObject($node, $remote_id, $remote_type);
    }
    else {
      // Remove this item from the index if it exists.
      if ($item_record !== NULL) {
        return $this->deleteEntityByItemRecord($item_record);
      }
    }
  }

  /**
   * Sync a Learning Object entity with recommendation engine.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that was updated.
   * @param string $remote_id
   *   The existing recommendation engine id for this node.
   * @param string $remote_type
   *   The existing object type on the recommendation engine.
   */
  protected function updateLearningObject(NodeInterface $node, $remote_id = NULL, $remote_type = NULL) {
    // Make sure subtopic prerequisites get updated too.
    $this->updateSubTopicPrerequisiteRelationships($remote_id);
    // Check for Nested Tip cards.
    if ($node->hasField('field_tip_card')) {
      foreach ($node->field_tip_card->referencedEntities() as $tipcard) {
        $item_record = $this->getItemRecordByEntity($tipcard);
        $item_record = !empty($item_record) ? $item_record[0] : NULL;
        $this->updateNestableLearningObject($tipcard, $item_record);
      }
    }
    // Check for Nested Quizes.
    if ($node->hasField('field_flash_card')) {
      foreach ($node->field_flash_card->referencedEntities() as $flashcard) {
        $item_record = $this->getItemRecordByEntity($flashcard);
        $item_record = !empty($item_record) ? $item_record[0] : NULL;
        $this->updateNestableLearningObject($flashcard, $item_record);
      }
    }
    // Check for Nested Flashcards.
    if ($node->hasField('field_quiz')) {
      foreach ($node->field_quiz->referencedEntities() as $quiz) {
        $item_record = $this->getItemRecordByEntity($quiz);
        $item_record = !empty($item_record) ? $item_record[0] : NULL;
        $this->updateNestableLearningObject($quiz, $item_record);
      }
    }

    return TRUE;
  }

  /**
   * Sync a taxonomy entity with recommendation engine.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term that was updated.
   * @param string $remote_id
   *   The existing recommendation engine id for this term.
   * @param string $remote_type
   *   The existing object type on the recommendation engine.
   */
  protected function updateTopic(TermInterface $term, $remote_id = NULL, $remote_type = NULL) {
    $endpoint_url = $this->generateEndpointUrl(self::RE_TOPIC_ENDPOINT);
    $httpMethod = ($remote_id) ? self::PUT : self::POST;
    $content = [
      'id' => ($remote_id) ? $remote_id : '0',
      'name' => $term->label(),
      'description' => $this->getItemRecordId($term),
      'label' => 'Topic',
      'published' => $term->isPublished(),
    ];

    $response = $this->makeRequest($endpoint_url, $httpMethod, $content);
    if (!$response || $response['status_code'] != 200 || $response['body'] == NULL) {
      // Failed to save it to recommendation engine so queue locally.
      $this->createOrUpdateItemRecord($term, self::STATUS_NEEDS_SYNC, $remote_id, $remote_type);
      return FALSE;
    }
    $remote_id = ($remote_id) ?: $response['body'];
    $this->createOrUpdateItemRecord($term, self::STATUS_SYNCED, $remote_id, 'Topic');
    // Contains relationships are updated on Node edit.
    // Add prerequisite reslationships.
    // Get a list of existing prerequisites
    // Get an array of items that contain this item.
    $existing_relationships = $this->getExistingRelationships('.*', $remote_id, 'prerequisite');

    // Remove any prerequisites that no longer exist.
    $this->removeRelations($existing_relationships);
    // Parent relationships need to be added here.
    // Get existing parents.
    $existing_relationships = $this->getExistingRelationships('.*', $remote_id, 'contains');
    if ($parents = $this->entityTypeManager->getStorage("taxonomy_term")->loadParents($term->id())) {
      // Add contains relationship to parent.
      foreach ($parents as $parent) {
        if ($item_record = $this->getItemRecordByEntity($parent, self::CREATE_IF_NEEDED)) {
          if (!$item_record[0]->remote_id) {
            // Referenced item hasn't been sync'd so has no id.
            // Set this topic to need sync again in future and continue.
            $this->createOrUpdateItemRecord($term, self::STATUS_NEEDS_SYNC, $remote_id, $remote_type);
            continue;
          }
          $this->addRelation($item_record[0]->remote_id, $remote_id, 'contains');
          unset($existing_relationships[$this->getRelationshipsId($item_record[0]->remote_id, $remote_id, 'contains')]);
        }
      }
    }
    else {
      // No parent so need to add default parent to graph.
      if ($item_record = $this->getDefaultTopic()) {
        if (!$item_record[0]->remote_id) {
          // Referenced item hasn't been sync'd so has no id.
          // Set this topic to need sync again in future and continue.
          $this->createOrUpdateItemRecord($term, self::STATUS_NEEDS_SYNC, $remote_id, $remote_type);
        }
        else {
          $this->addRelation($item_record[0]->remote_id, $remote_id, 'contains');
          unset($existing_relationships[$this->getRelationshipsId($item_record[0]->remote_id, $remote_id, 'contains')]);
        }
      }
    }
    // Remove any old relationship that are no longer needed.
    $this->removeRelations($existing_relationships);
    // Check and create if neccessary our learning phase subtopics.
    foreach (self::PHASE_SUFFIXES as $phase) {
      $this->getOrCreateSubTopic($this->getItemRecordId($term), $phase, $remote_id, $term);
    }
    return TRUE;
  }

  /**
   * Gaurantee correct ordering by learning phase of LOS in topics.
   *
   * To do this we need to split the topic up in to major learning phases.
   */
  protected function getOrCreateSubTopic($parent_record_id, $phase, $parent_remote_id, $parent_term) {
    $conditions = [
      'item_id' => $parent_record_id . '-' . $phase,
      'plugin_id' => $this->getPluginId(),
    ];
    $item_record = $this->getItemRecords($conditions);
    if (empty($item_record)) {
      // We need to create this Topic.
      $endpoint_url = $this->generateEndpointUrl(self::RE_TOPIC_ENDPOINT);
      $httpMethod = self::POST;
      $content = [
        'id' => '0',
        'name' => $parent_term->label() . '-' . $phase,
        'description' => $parent_record_id . '-' . $phase,
        'label' => 'Topic',
        'published' => $parent_term->isPublished(),
      ];

      $response = $this->makeRequest($endpoint_url, $httpMethod, $content);
      if (!$response || $response['status_code'] != 200 || $response['body'] == NULL) {
        return FALSE;
      }
      $remote_id = $response['body'];
      $conditions['remote_id'] = $remote_id;
      $conditions['remote_type'] = 'Topic';
      $conditions['item_type'] = 'custom:subtopic';
      $conditions['status'] = self::STATUS_SYNCED;
      $this->createItemRecord($conditions);
      // Need to add the contains relationship too.
      $this->addRelation($parent_remote_id, $remote_id, 'contains');

      // Get item record to return.
      $conditions = [
        'item_id' => $parent_record_id . '-' . $phase,
        'plugin_id' => $this->getPluginId(),
      ];
      $item_record = $this->getItemRecords($conditions);
    }
    return $item_record;
  }

  /**
   * Delete subtopic items from graph and from local record.
   */
  protected function deleteSubtopics($parent_record_id) {
    foreach (self::PHASE_SUFFIXES as $phase) {
      $conditions = [
        'item_id' => $parent_record_id . '-' . $phase,
        'plugin_id' => $this->getPluginId(),
      ];
      $item_record = $this->getItemRecords($conditions);
      if (!empty($item_record)) {
        $this->deleteEntityByItemRecord($item_record[0]);
      }

    }
  }

  /**
   * This method updates prerequisite relations between subtopics.
   *
   * We add prerequisite relationships to enforce recommendations to
   * have the correct learning phase. However, if no content exists in
   * a lower learning phase we allow recommendations from higher learning
   * phases.  We can only use LO remote id for this as when an entity is
   * deleted we might not have access to more details.
   */
  protected function updateSubTopicPrerequisiteRelationships($lo_remote_id, $deleted = FALSE) {
    // Get all subtopics containing this item.
    $related_subtopics = $this->getRelatedSubtopics($lo_remote_id);
    // Load sibling subtopics.
    foreach ($related_subtopics as $id => $item_record) {
      // Get root of $id.
      $base_id = explode('-', $id);
      $siblings = $this->loadSiblingSubtopics($base_id[0]);
      $lo_counts = $this->countLosInSubtopics($siblings);
      // Adjust count if node is being deleted.
      if ($deleted && isset($base_id[1]) && $lo_counts[$base_id[1]] > 0) {
        $lo_counts[$base_id[1]]--;
      }
      // If Explore has no data remove prerequisite relationships.
      if (isset($lo_counts[self::PHASE_SUFFIXES[0]]) && $lo_counts[self::PHASE_SUFFIXES[0]] == 0) {
        if (isset($siblings[self::PHASE_SUFFIXES[1]]) && isset($siblings[self::PHASE_SUFFIXES[0]])) {
          $this->removeRelation(
            $siblings[self::PHASE_SUFFIXES[1]]->remote_id,
            $siblings[self::PHASE_SUFFIXES[0]]->remote_id,
            'prerequisite');
        }
        if (isset($siblings[self::PHASE_SUFFIXES[2]]) && isset($siblings[self::PHASE_SUFFIXES[0]])) {
          $this->removeRelation(
            $siblings[self::PHASE_SUFFIXES[2]]->remote_id,
            $siblings[self::PHASE_SUFFIXES[0]]->remote_id,
            'prerequisite');
        }
      }
      elseif (!$deleted) {
        // We need to add these relationships.
        if (isset($siblings[self::PHASE_SUFFIXES[1]]) && isset($siblings[self::PHASE_SUFFIXES[0]])) {
          $this->addRelation(
            $siblings[self::PHASE_SUFFIXES[1]]->remote_id,
            $siblings[self::PHASE_SUFFIXES[0]]->remote_id,
            'prerequisite');
        }
        if (isset($siblings[self::PHASE_SUFFIXES[2]]) && isset($siblings[self::PHASE_SUFFIXES[0]])) {
          $this->addRelation(
            $siblings[self::PHASE_SUFFIXES[2]]->remote_id,
            $siblings[self::PHASE_SUFFIXES[0]]->remote_id,
            'prerequisite');
        }
      }
      // If Study has no data remove prerequisite relationships.
      if (isset($lo_counts[self::PHASE_SUFFIXES[1]]) && $lo_counts[self::PHASE_SUFFIXES[1]] == 0) {
        if (isset($siblings[self::PHASE_SUFFIXES[2]]) && isset($siblings[self::PHASE_SUFFIXES[1]])) {
          $this->removeRelation(
            $siblings[self::PHASE_SUFFIXES[2]]->remote_id,
            $siblings[self::PHASE_SUFFIXES[1]]->remote_id,
            'prerequisite');
        }
      }
      elseif (!$deleted) {
        // We need to add these relationships.
        if (isset($siblings[self::PHASE_SUFFIXES[2]]) && isset($siblings[self::PHASE_SUFFIXES[1]])) {
          $this->addRelation(
            $siblings[self::PHASE_SUFFIXES[2]]->remote_id,
            $siblings[self::PHASE_SUFFIXES[1]]->remote_id,
            'prerequisite');
        }
      }
      // Sharpen is never a prerequisite as it is the top level.
    }

  }

  /**
   * Get the subtopics that contain a given remote_id.
   */
  protected function getRelatedSubtopics($lo_remote_id) {
    $pattern = $this->getRelationshipsId('.*', $lo_remote_id, 'contains');
    $related_subtopics = [];
    // Find all the subtopics related to lo.
    foreach ($this->getItemRecordByIdRegEx($pattern) as $item) {
      $relation_ids = explode(self::ID_SEPARATOR, $item->item_id);
      $item_record = $this->getItemRecords(
        [
          'plugin_id' => $this->getPluginId(),
          'remote_id' => $relation_ids[2],
          'item_type' => 'custom:subtopic',
        ]
        );
      if (is_array($item_record) && isset($item_record[0])) {
        $related_subtopics[$item_record[0]->item_id] = $item_record[0];
      }
    }
    return $related_subtopics;
  }

  /**
   * Count the number of LOs contained in a subtopic.
   */
  protected function countLosInSubtopics(array $subtopics) {
    $subtopic_lo_counts = [];
    foreach ($subtopics as $phase => $item_record) {
      $pattern = $this->getRelationshipsId($item_record->remote_id, '.*', 'contains');
      $result = $this->getItemRecordByIdRegEx($pattern);
      if (is_array($result)) {
        $subtopic_lo_counts[$phase] = count($result);
      }
      else {
        $subtopic_lo_counts[$phase] = 0;
      }
    }
    return $subtopic_lo_counts;
  }

  /**
   * Load subtopics related to a base id.
   */
  protected function loadSiblingSubtopics($base_id) {
    $subtopics = [];
    foreach (self::PHASE_SUFFIXES as $phase) {
      $conditions =
      [
        'plugin_id' => $this->getPluginId(),
        'item_id' => $base_id . '-' . $phase,
      ];
      $record = $this->getItemRecords($conditions);
      if (isset($record[0])) {
        $subtopics[$phase] = $record[0];
      }
    }
    return $subtopics;
  }

  /**
   * Check for subtopic completeness.
   */
  protected function checkSubtopicCompleteness(UserInterface $user, EntityInterface $entity) {
    if (!($user_item_record = $this->getItemRecordByEntity($user, self::CREATE_IF_NEEDED)) || $user_item_record[0]->remote_id == NULL) {
      return FALSE;
    }
    // Check if referenced Entity exists.
    if (
      !($entity_item_record = $this->getItemRecordByEntity($entity, self::CREATE_IF_NEEDED))
      || $entity_item_record[0]->remote_id == NULL
      || $entity_item_record[0]->remote_type != 'LearningObject'
      ) {
      return FALSE;
    }

    // Get all contains relationships that are subtopics.
    $related_subtopics = $this->getRelatedSubtopics($entity_item_record[0]->remote_id);
    // Check to see if this subtopic is complete.
    foreach ($related_subtopics as $subtopic) {
      if ($this->isSubtopicComplete($subtopic, $user_item_record)) {
        // Set it complete.
        $replacements = [
          '{username}' => $user->id(),
          '{vertexId}' => urlencode($subtopic->remote_id),
        ];
        $endpoint_url = $this->generateEndpointUrl(self::RE_TOPIC_COMPETENCY, $replacements);
        $content = ['competency' => 'COMPETENT'];
        $response = $this->makeRequest($endpoint_url, self::POST, $content);
      }
    }

  }

  /**
   * Checks to see if a subtopic is complete.
   */
  protected function isSubtopicComplete($item_record, $user_record) {
    // Get a list of all items contained by this subtopic.
    $children = $this->getExistingRelationships(
      $item_record->remote_id,
      '.*',
      'contains'
    );
    $children = array_keys($children);
    // Check for Userstatus complete for for each child item.
    foreach ($children as $item_id) {
      $item_id_parts = explode(self::ID_SEPARATOR, $item_id);
      if (!isset($item_id_parts[3])) {
        return FALSE;
      }
      $conditions = [
        'plugin_id' => $this->getPluginId(),
        'remote_type' => $this->generateUserStatusType('completed', $user_record[0]->remote_id, $item_id_parts[3]),
      ];
      $results = $this->getItemRecords($conditions);
      if (!is_array($results) || empty($results)) {
        // Didn't find a complete status so topic is not complete.
        return FALSE;
      }

    }

    return TRUE;
  }

  /**
   * Sync a taxonomy entity with recommendation engine.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that was updated.
   * @param string $remote_id
   *   The existing recommendation engine id for this node.
   * @param string $remote_type
   *   The existing object type on the recommendation engine.
   */
  protected function updateCourse(NodeInterface $node, $remote_id = NULL, $remote_type = NULL) {
    $endpoint_url = $this->generateEndpointUrl(self::RE_COURSE_ENDPOINT);
    $httpMethod = ($remote_id) ? self::PUT : self::POST;
    $content = [
      'id' => ($remote_id) ? $remote_id : '0',
      'name' => $node->label(),
      'description' => $this->getItemRecordId($node),
      'label' => 'Course',
      'published' => $node->isPublished(),
    ];

    $response = $this->makeRequest($endpoint_url, $httpMethod, $content);
    if (!$response || $response['status_code'] != 200 || $response['body'] == NULL) {
      // Failed to save it to recommendation engine so queue locally.
      $this->createOrUpdateItemRecord($node, self::STATUS_NEEDS_SYNC, $remote_id, $remote_type);
      return FALSE;
    }
    $remote_id = ($remote_id) ?: $response['body'];
    $this->createOrUpdateItemRecord($node, self::STATUS_SYNCED, $remote_id, 'Course');
    // Add topic relationship.
    $existing_relationships = $this->getExistingRelationships('.*', $remote_id, 'contains');
    // Update Topic.
    if ($topic_list = $node->field_topic) {
      $topic_list = $topic_list->referencedEntities();
      foreach ($topic_list as $topic) {
        if ($item_record = $this->getItemRecordByEntity($topic, self::CREATE_IF_NEEDED)) {
          if (!$item_record[0]->remote_id) {
            // Referenced item hasn't been sync'd so has no id.
            // Set this node to need sync again in future and continue.
            $this->createOrUpdateItemRecord($node, self::STATUS_NEEDS_SYNC, $remote_id, 'Course');
            continue;
          }
          $this->addRelation($item_record[0]->remote_id, $remote_id, 'contains');
          unset($existing_relationships[$this->getRelationshipsId($item_record[0]->remote_id, $remote_id, 'contains')]);
        }
      }
    }
    // Remove topic relationships that are no longer valid.
    $this->removeRelations($existing_relationships);
    // Add contains relationships.
    // Find existing relationships.
    $existing_relationships = $this->getExistingRelationships($remote_id, '.*', 'contains');
    $child_los = [];
    if ($node_list = $node->field_learning_content) {
      $node_list = $node_list->referencedEntities();
      foreach ($node_list as $learningObject) {
        if ($item_record = $this->getItemRecordByEntity($learningObject, self::CREATE_IF_NEEDED)) {
          if (!$item_record[0]->remote_id) {
            // Referenced item hasn't been sync'd so has no id.
            // Set this course to need sync again in future and continue.
            $this->createOrUpdateItemRecord($node, self::STATUS_NEEDS_SYNC, $remote_id, $remote_type);
            continue;
          }
          // Adding an item to course removes it from topic in RE.
          // To do this we update the learning Object here.
          $this->updateLearningObject($learningObject, $item_record[0]->remote_id, 'learningObject');
          $this->addRelation($remote_id, $item_record[0]->remote_id, 'contains');
          unset($existing_relationships[$this->getRelationshipsId($remote_id, $item_record[0]->remote_id, 'contains')]);
          $child_los[] = $item_record[0]->remote_id;
        }
      }
    }
    // We need to update learning nodes that have been removed from this course
    // to get them back into topic.
    foreach ($existing_relationships as $item_id => $remote_id) {
      // Attempt to load the learningObject.
      // relation:type:source:sink we want sink.
      $details = explode(self::ID_SEPARATOR, $item_id);
      if ($learningObject = $this->loadEntityByRemoteId($details[3])) {
        $this->updateLearningObject($learningObject, $details[3]);
      }
    }
    // If course has child LOs attempt to save order.
    if (!empty($child_los)) {
      $content = [
        'id' => $remote_id,
        'order' => $child_los,
      ];

      $response = $this->makeRequest($endpoint_url, self::PUT, $content);
      if (!$response || $response['status_code'] != 200 || $response['body'] == NULL) {
        // Failed to save it to recommendation engine so queue locally.
        $this->createOrUpdateItemRecord($node, self::STATUS_NEEDS_SYNC, $remote_id, $remote_type);
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Sync a flag entity with recommendation engine.
   *
   * @param \Drupal\flag\FlaggingInterface $flag
   *   The flag that was updated.
   * @param string $remote_id
   *   The existing recommendation engine id for this flag.
   * @param string $remote_type
   *   The existing object type on the recommendation engine.
   */
  protected function updateFlagging(FlaggingInterface $flag, $remote_id = NULL, $remote_type = NULL) {
    // If it already exists just return.
    if ($remote_id) {
      return TRUE;
    }
    $user = $flag->getOwner();
    $entity = $this->entityTypeManager->getStorage($flag->getFlaggableType())->load($flag->getFlaggableId());

    // Check to see if this is flash card, tip card or quiz
    // that is associated with a learning object. If it is
    // we don't track them via them.
    if ($entity->getEntityTypeId() == 'node'
         && in_array($entity->bundle(), ['flash_card', 'tip_card', 'quiz'])
         && $entity->hasField('field_parent_content')
         && !$entity->field_parent_content->isEmpty()
    ) {
      // Delete item record if necessary.
      $this->deleteItemRecordByEntity($flag);
      return TRUE;
    }

    // Check if the user exists.
    if (!($user_item_record = $this->getItemRecordByEntity($user, self::CREATE_IF_NEEDED)) || $user_item_record[0]->remote_id == NULL) {
      $this->createOrUpdateItemRecord($flag, self::STATUS_NEEDS_SYNC, $remote_id, 'UserStatus');
      return FALSE;
    }
    // Check if referenced Entity exists.
    if (!($item_record = $this->getItemRecordByEntity($entity, self::CREATE_IF_NEEDED)) || $item_record[0]->remote_id == NULL) {
      $this->createOrUpdateItemRecord($flag, self::STATUS_NEEDS_SYNC, $remote_id, 'UserStatus');
      return FALSE;
    }
    // Shared values.
    $replacements = [
      '{username}' => $user->id(),
      '{vertexId}' => urlencode($item_record[0]->remote_id),
    ];
    $content = [];

    switch ($flag->bundle()) {
      case 'bookmark':
        if ($entity->bundle() == 'course') {
          $type = 'userEnroll';
          $replacements['{edgeType}'] = 'userEnroll';
        }
        else {
          $type = 'userBookmark';
          $replacements['{edgeType}'] = 'userBookmark';
        }
        $endpoint_url = $this->generateEndpointUrl(self::RE_USER_RELATION, $replacements);
        break;

      case 'completed':
        // Courses can't be completed.
        if ($entity->bundle() == 'course') {
          $this->deleteItemRecordByEntity($flag);
          return FALSE;
        }
        $type = $this->generateUserStatusType('completed', $user_item_record[0]->remote_id, $item_record[0]->remote_id);
        $replacements['{type}'] = 'completed';
        $endpoint_url = $this->generateEndpointUrl(self::RE_USERSTATUS_RELATION, $replacements);
        break;

      case 'seen':
        if ($entity->bundle() == 'course') {
          $this->deleteItemRecordByEntity($flag);
          return FALSE;
        }
        $type = $this->generateUserStatusType('seen', $user_item_record[0]->remote_id, $item_record[0]->remote_id);
        $replacements['{type}'] = 'seen';
        $endpoint_url = $this->generateEndpointUrl(self::RE_USERSTATUS_RELATION, $replacements);
        break;

      case 'topic_completed':
        $type = 'topicCompleted';
        $endpoint_url = $this->generateEndpointUrl(self::RE_TOPIC_COMPETENCY, $replacements);
        $content = ['competency' => 'COMPETENT'];
        break;

      default:
        return FALSE;
    }

    $response = $this->makeRequest($endpoint_url, self::POST, $content);
    if (!$response || $response['status_code'] != 200 || $response['body'] == NULL) {
      // Failed to save it to recommendation engine so queue locally.
      $this->createOrUpdateItemRecord($flag, self::STATUS_NEEDS_SYNC, $remote_id, $type);
      return FALSE;
    }
    $this->createOrUpdateItemRecord($flag, self::STATUS_SYNCED, $response['body'], $type);
    // Queue user for recommendations if completed statment.
    if ($flag->bundle() == 'completed') {
      $this->checkSubtopicCompleteness($user, $entity);
      $this->queueUserForRecommendations($user);
    }
    return TRUE;
  }

  /**
   * Return a string in correct format for UserStatus type.
   */
  protected function generateUserStatusType($action, $user_remote_id, $entity_remote_id) {
    return 'userStatus' . self::ID_SEPARATOR . $action . self::ID_SEPARATOR . $user_remote_id . self::ID_SEPARATOR . $entity_remote_id;
  }

  /**
   * Check recommendation engine for a relationship.
   */
  protected function checkForExistingRelationship($source, $sink, $type) {
    $conditions = [
      'item_id' => $this->getRelationshipsId($source, $sink, $type),
      'plugin_id' => $this->pluginId,
    ];
    if ($item_record = $this->getItemRecords($conditions)) {
      if (isset($item_record[0]->remote_id)) {
        return $item_record[0]->remote_id;
      }
    }
    return "";
  }

  /**
   * Add a relation edge to the graph.
   */
  protected function addRelation($source, $sink, $type) {
    // Check to see if relationship already exists.
    if (!empty($this->checkForExistingRelationship($source, $sink, $type))) {
      // Already exists.
      return TRUE;
    }
    $endpoint_url = $this->generateEndpointUrl(self::RE_RELATION_ENDPOINT);
    $content = [
      'label' => $type,
      'out' => $source,
      'in' => $sink,
    ];

    $response = $this->makeRequest($endpoint_url, self::POST, $content);
    if (!$response || $response['status_code'] != 200) {
      return FALSE;
    }
    return $this->createItemRecord(
      [
        'plugin_id' => $this->pluginId,
        'item_id' => $this->getRelationshipsId($source, $sink, $type),
        'item_type' => 'custom',
        'remote_id' => $response['body'],
        'remote_type' => 'relation',
        'status' => self::STATUS_SYNCED,
      ]
    );
  }

  /**
   * Remove a relation edge to the graph.
   */
  protected function removeRelation($source, $sink, $type) {
    // Check to see if relationship already exists.
    if ($relation_id = $this->checkForExistingRelationship($source, $sink, $type)) {
      $replacements = [
        '{id}' => urlencode($relation_id),
      ];
      $endpoint = $this->generateEndpointUrl(self::RE_DELETE_OBJECT_ENDPOINT, $replacements);
      $response = $this->makeRequest($endpoint, self::DELETE);
      if ($response['status_code'] == 200 || $response['status_code'] == 401) {
        return $this->deleteItemRecords([
          'plugin_id' => $this->pluginId,
          'item_id' => $this->getRelationshipsId($source, $sink, $type),
          'remote_id' => $relation_id,
        ]);
      }
    }
    // No relationship existed.
    return TRUE;
  }

  /**
   * Remove an array of relations from graph.
   */
  protected function removeRelations(array $relations) {
    $endpoint = $this->generateEndpointUrl(self::RE_DELETE_OBJECTS_ENDPOINT);
    $response = $this->makeRequest($endpoint, self::DELETE, array_values($relations));
    if ($response['status_code'] == 200 || $response['status_code'] == 401) {
      foreach ($relations as $item_id => $remote_id) {
        $this->deleteItemRecords([
          'plugin_id' => $this->pluginId,
          'item_id' => $item_id,
          'remote_id' => $remote_id,
        ]);
      }
    }
    return TRUE;
  }

  /**
   * Add a user relation edge to the graph.
   */
  protected function addUserRelation($source, $username, $sink, $type) {
    // Check to see if relationship already exists.
    if (!empty($this->checkForExistingRelationship($source, $sink, $type))) {
      // Already exists.
      return TRUE;
    }
    // Create relationship.
    $replacements = [
      '{username}' => $username,
      '{edgeType}' => $type,
      '{vertexId}' => urlencode($sink),
    ];
    $endpoint_url = $this->generateEndpointUrl(self::RE_USER_RELATION, $replacements);
    $response = $this->makeRequest($endpoint_url, self::POST);
    if (!$response || $response['status_code'] != 200) {
      return FALSE;
    }
    return $this->createItemRecord(
      [
        'plugin_id' => $this->pluginId,
        'item_id' => $this->getRelationshipsId($source, $sink, $type),
        'item_type' => 'custom',
        'remote_id' => $response['body'],
        'remote_type' => 'relation',
        'status' => self::STATUS_SYNCED,
      ]
    );
  }

  /**
   * Get the default corpus topic.
   *
   * @return array
   *   The item records result of this search.
   */
  protected function getDefaultTopic() {
    $conditions = [
      'plugin_id' => $this->pluginId,
      'item_id' => 'custom:default:topic',
      'item_type' => 'custom:default',
    ];
    $currentRecord = $this->getItemRecords($conditions);
    if (empty($currentRecord)) {
      // Attempt to add special default topic.
      $endpoint_url = $this->generateEndpointUrl(self::RE_TOPIC_ENDPOINT);
      $httpMethod = self::POST;
      $content = [
        'id' => '0',
        'name' => 'Default Topic Parent',
        'description' => 'custom:default:topic',
        'label' => 'Topic',
        'published' => 1,
      ];

      $response = $this->makeRequest($endpoint_url, $httpMethod, $content);
      if (!$response || $response['status_code'] != 200 || $response['body'] == NULL) {
        return FALSE;
      }
      $remote_id = $response['body'];
      // Create the record.
      try {
        // Create record.
        $query = $this->database->insert('perls_recommendation_item');
        $updates['status'] = self::STATUS_SYNCED;
        // Entity fields are conditions for updates.
        foreach ($conditions as $key => $value) {
          $updates[$key] = $value;
        }
        // Add some default values.
        $updates['remote_id'] = $remote_id;
        $updates['remote_type'] = 'Topic';
        $query->fields($updates);
        $query->execute();
      }
      catch (\Exceptiopn $e) {
      }
      // Get the newly created record.
      $currentRecord = $this->getItemRecords($conditions);
    }
    return $currentRecord;
  }

  /**
   * Get an array of existing relationships.
   */
  protected function getExistingRelationships($source, $sink, $type) {
    $pattern = $this->getRelationshipsId($source, $sink, $type);
    $relationships = [];
    foreach ($this->getItemRecordByIdRegEx($pattern) as $item) {
      $relationships[$item->item_id] = $item->remote_id;
    }
    return $relationships;

  }

  /**
   * Get an array of existing relationships.
   */
  protected function getRelationshipsId($source, $sink, $type) {
    return 'relation' . self::ID_SEPARATOR . $type . self::ID_SEPARATOR . $source . self::ID_SEPARATOR . $sink;
  }

  /**
   * Load entity by remote id.
   */
  protected function loadEntityByRemoteId(string $remote_id) {
    if ($remote_id == "") {
      return NULL;
    }
    $conditions = [
      'plugin_id' => $this->pluginId,
      'remote_id' => $remote_id,
    ];
    $item_records = $this->getItemRecords($conditions);
    // If item records is null or empty return FALSE.
    if ($item_records === NULL || empty($item_records)) {
      return NULL;
    }
    $entity_details = $item_records[0]->item_id;
    $entity_details = explode(self::ITEM_RECORD_TYPE_SEPERATOR, $entity_details);
    // Load the entity.
    return $this->entityTypeManager->getStorage($entity_details[1])->load($entity_details[2]);
  }

  /**
   * Magic method implementation to serialize this plugin.
   *
   * There is a bug in php where nested configurations get corrupted
   * when serialized.
   *
   * @return array
   *   The names of all variables that should be serialized.
   */
  public function __sleep() {
    // Limit to only the required data which is needed to properly restore the
    // state during unserialization.
    $this->serializationData = [
      'pluginId' => $this->pluginId,
    ];
    return ['serializationData'];
  }

  /**
   * Magic method implementation to unserialize this plugin.
   *
   * There is a bug in php where nested configurations get corrupted
   * when serialized.
   */
  public function __wakeup() {
    $this->pluginId = $this->serializationData['pluginId'];
    $this->config = \Drupal::service('config.factory')->get('perls_recommendation.settings');
    $this->logger = \Drupal::service('logger.factory')->get('Perls Recommendation Engine');
    $this->httpClient = \Drupal::service('http_client');
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->currentUser = \Drupal::service('current_user');
    $this->database = \Drupal::database('database');
    unset($this->serializationData);
  }

}
