<?php

namespace Drupal\perls_recommendation;

use Drupal\Core\Config\Config;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\perls_recommendation\Entity\UserRecommendationStatus;
use Drupal\user\UserInterface;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an base class for Remote Recommendation Engine plugins.
 *
 * In addition to all the functionality of RecommendationEnginePluginBase,
 * this class adds basic http communication methods.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_recommendation_engine_info_alter(). The definition includes the
 * following keys:
 * - id: The unique, system-wide identifier of the recommendation class.
 * - label: The human-readable name of the recommendation class, translated.
 * - description: A human-readable description for the recommendation class,
 *   translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @RecommendationEngine(
 *   id = "my_recommendation_engine",
 *   label = @Translation("My Recommendation Engine"),
 *   description = @Translation("Uses my super recommendation engine to recommend content.")
 * )
 * @endcode
 *
 * @see \Drupal\perls_recommendation\Annotation\RecommendationEngine
 * @see \Drupal\perls_recommendation\RecommendationEnginePluginManager
 * @see \Drupal\perls_recommendation\RecommendationEnginePluginInterface
 * @see plugin_api
 */
abstract class RemoteRecommendationEnginePluginBase extends RecommendationEnginePluginBase {

  /**
   * Http Methods.
   */
  const GET = 'get';
  const PUT = 'put';
  const POST = 'post';
  const DELETE = 'delete';

  /**
   * The seperator used in the database to seperate parts of entity id.
   */
  const ITEM_RECORD_TYPE_SEPERATOR = ':';
  /**
   * Status states.
   */
  const STATUS_SYNCED = 1;
  const STATUS_NEEDS_SYNC = 0;

  /**
   * Create if needed const.
   */
  const CREATE_IF_NEEDED = TRUE;

  /**
   * The http client.
   *
   * @var \Guzzlehttp\Client
   */
  protected $httpClient;

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('Remote Recommendation Engine Plugin'),
      $container->get('http_client'),
      $container->get('database'),
      $container->get('entity_type.manager')
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
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory);
    $this->httpClient = $http_client;
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return [
      'status' => FALSE,
      'description' => $this->t('Plugin has no implementation of getStatus()'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array &$form, FormStateInterface $form_state, Config $config) {
    // Add a status message to top of this page.
    $status = $this->getStatus();
    if ($status['status'] === TRUE) {
      $this->messenger()->addStatus($this->getPluginDefinition()['label'] . ' ' . $status['description']);
    }
    else {
      $this->messenger()->addWarning($this->getPluginDefinition()['label'] . ' ' . $status['description']);
    }
    $form[$this->pluginId . '_url'] = [
      '#default_value' => $config->get($this->pluginId . '_url') ?: 'http://172.21.0.50',
      '#description' => $this->t('The URL of the recommendation engine. For local docker instances try http://172.21.0.50'),
      '#maxlength' => 512,
      '#placeholder' => 'https://www.example.com',
      '#size' => 80,
      '#title' => $this->t('Recommendation Engine Url'),
      '#type' => 'textfield',
      '#states'        => [
        'visible' => [
          ':input[name="' . $this->pluginId . '_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form[$this->pluginId . '_port'] = [
      '#default_value' => $config->get($this->pluginId . '_port'),
      '#description' => $this->t('The port to use when contacting the recommendation engine.'),
      '#maxlength' => 512,
      '#placeholder' => '8080',
      '#size' => 40,
      '#title' => $this->t('Recommendation Engine Port'),
      '#type' => 'textfield',
      '#states'        => [
        'visible' => [
          ':input[name="' . $this->pluginId . '_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $total = count($this->getItemRecords([]));
    $indexed = count($this->getItemRecords(['status' => self::STATUS_SYNCED]));
    $precentage = ($total) ? ($indexed / $total) * 100 : 0;

    $form[$this->pluginId . '_status'] = [
      '#type' => 'details',
      '#title' => ($status['status']) ? $this->t('Current Status: Connected') : $this->t('Current Status: Disconnected'),
      '#description' => $status['description'],
      '#open' => TRUE,
    ];
    $form[$this->pluginId . '_status']['status'] = [
      '#theme' => 'progress_bar',
      '#percent' => $precentage,
      '#message' => [
        '#markup' => $this->t(
          '<b>%indexed</b> items of <b> %total </b> synchronized with recommendation engine.',
          ['%indexed' => $indexed, '%total' => $total]
        ),
      ],
      '#label' => $this->t('Indexing Status'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if ($values[$this->pluginId . '_enabled'] && $values[$this->pluginId . '_url'] === '') {
      $form_state->setErrorByName($this->pluginId . '_url', $this->t('Url cannot be null for this recommendation engine'));
    }
    if ($values[$this->pluginId . '_enabled'] && $values[$this->pluginId . '_port'] === '') {
      $form_state->setErrorByName($this->pluginId . '_url', $this->t('Port cannot be null for this recommendation engine'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state, Config $config) {
    $values = $form_state->getValues();
    $config
      ->set($this->pluginId . '_url', $values[$this->pluginId . '_url'])
      ->set($this->pluginId . '_port', $values[$this->pluginId . '_port'])
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function syncGraph($batch_size = 100) {
    // Check status of connection to recommendation engine.
    $status = $this->getStatus();
    if (!$status['status']) {
      return FALSE;
    }
    // Get up to $batch_size item_records that need sync.
    $conditions = [
      'plugin_id' => $this->pluginId,
      'status' => self::STATUS_NEEDS_SYNC,
    ];
    $item_records = $this->getItemRecords($conditions);
    // If item records is null or empty return FALSE.
    if ($item_records === NULL || empty($item_records)) {
      return FALSE;
    }

    foreach ($item_records as $item_record) {
      if (strpos($item_record->item_type, 'custom') == 0) {
        continue;
      }
      $entity_id = $item_record->item_id;
      $entity_id = explode(self::ITEM_RECORD_TYPE_SEPERATOR, $entity_id);
      // Load the entity.
      $entity = $this->entityTypeManager->getStorage($entity_id[1])->load($entity_id[2]);
      if ($entity) {
        $this->updateEntity($entity);
      }
      else {
        $this->deleteEntityByItemRecord($item_record);
      }
    }

    return TRUE;
  }

  /**
   * Delete Entity by Item Record.
   *
   * This function removes the current item record from database.
   * Extending classes should also remove remote objects using this method.
   */
  protected function deleteEntityByItemRecord(object $item_record) {
    $conditions = [
      'plugin_id' => $this->getPluginId(),
      'remote_id' => $item_record->remote_id,
      'item_id' => $item_record->item_id,
    ];
    return $this->deleteItemRecords($conditions);
  }

  /**
   * Send request to recommendation engine.
   *
   * @param string $endpoint
   *   The Url endpoint.
   * @param string $method
   *   Is the request a GET, PUT, POST or DELETE.
   * @param array $content
   *   An array that will be converted to Json and added to body.
   *
   * @return array
   *   The status_code and body in an array.
   */
  protected function makeRequest($endpoint, $method = self::GET, array $content = []) {
    $body = [
      'json' => $content,
    ];
    try {
      switch ($method) {
        case self::POST:
          $request = $this->httpClient->post($endpoint, $body);
          break;

        case self::PUT:
          $request = $this->httpClient->put($endpoint, $body);
          break;

        case self::DELETE:
          $request = $this->httpClient->delete($endpoint, $body);
          break;

        default:
          $request = $this->httpClient->get($endpoint, $body);
          break;
      }
      $response['body'] = json_decode($request->getBody());
      $response['status_code'] = $request->getStatusCode();
    }
    catch (\Exception $e) {
      $this->logger->error('Make Request failed! Method: ' . $method . ', Endpoint: ' . $endpoint . ', Body: ' . json_encode($body) . ', Error: ' . $e->getMessage());
      watchdog_exception('perls_recommendation', $e);
      return NULL;
    }
    return $response;
  }

  /**
   * Create a conditions array from an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to search for.
   *
   * @return array
   *   The conditions that can be found using entity.
   */
  protected function getConditionsFromEntity(EntityInterface $entity) {
    $conditions = [
      'plugin_id' => $this->pluginId,
      'item_id' => $this->getItemRecordId($entity),
      'item_type' => $this->getItemRecordType($entity),
    ];
    return $conditions;
  }

  /**
   * Get Item id from associated entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to search for.
   *
   * @return string
   *   Returns a primary id string in form "entity:TYPE:ID".
   */
  protected function getItemRecordId(EntityInterface $entity) {
    return $this->getItemRecordType($entity) . self::ITEM_RECORD_TYPE_SEPERATOR . $entity->id();
  }

  /**
   * Get Item Record type from associated entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to search for.
   *
   * @return string
   *   Returns a entity type string in form "entity:TYPE".
   */
  protected function getItemRecordType(EntityInterface $entity) {
    return 'entity' . self::ITEM_RECORD_TYPE_SEPERATOR . $entity->getEntityTypeId();
  }

  /**
   * Get the Item Records associated with this entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to search for.
   * @param bool $create_if_needed
   *   Attempt to add this entity if it doesn't exist.
   *
   * @return array
   *   The item records result of this search.
   */
  protected function getItemRecordByEntity(EntityInterface $entity, $create_if_needed = FALSE) {
    $currentRecord = $this->getItemRecords($this->getConditionsFromEntity($entity));
    if ($create_if_needed && empty($currentRecord)) {
      // Attempt to add this entity.
      $this->updateEntity($entity);
      // Get the newly created record.
      $currentRecord = $this->getItemRecords($this->getConditionsFromEntity($entity));
    }
    return $currentRecord;
  }

  /**
   * Get the Item Records using supplied conditions.
   *
   * @param array $conditions
   *   The array of conditions to add to the select statement.
   * @param array $fields
   *   The fields to return with this query.
   *
   * @return array
   *   The item records result of this search.
   */
  protected function getItemRecords(array $conditions = [], array $fields = []) {
    $transaction = $this->database->startTransaction();
    try {
      if (!isset($conditions['plugin_id'])) {
        $conditions['plugin_id'] = $this->pluginId;
      }
      $query = $this->database->select('perls_recommendation_item', 'pri');
      $query->fields('pri', $fields);
      foreach ($conditions as $key => $value) {
        $query->condition($key, $value);
      }
      $result = $query->execute();
      if ($result) {
        $result = $result->fetchAll();
      }
      return $result;
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      $transaction->rollBack();
      return NULL;
    }
  }

  /**
   * Get the Item Records associated with REGEX of itemID.
   *
   * @param string $item_id
   *   The RegEx pattern for item_id to search.
   *
   * @return array
   *   The item records result of this search.
   */
  protected function getItemRecordByIdRegEx(string $item_id) {
    $transaction = $this->database->startTransaction();
    try {
      $query = $this->database->select('perls_recommendation_item', 'pri');
      $query->fields('pri', []);
      $query->condition('plugin_id', $this->pluginId);
      $query->condition('item_id', $item_id, 'REGEXP');
      $result = $query->execute();
      if ($result) {
        $result = $result->fetchAll();
      }
      return $result;
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      $transaction->rollBack();
      return NULL;
    }
  }

  /**
   * Update Item record from and item record object.
   */
  protected function updateItemRecordStatus(object $item_record) {
    $transaction = $this->database->startTransaction();
    try {
      $conditions = [
        'plugin_id' => $item_record->plugin_id,
        'item_id' => $item_record->item_id,
      ];
      if ($item_record->remote_id) {
        $conditions['remote_id'] = $item_record->remote_id;
      }
      $updates = [
        'status' => $item_record->status,
      ];
      // Check to see if this item exists.
      if ($this->getItemRecords($conditions)) {
        // Update record.
        $query = $this->database->update('perls_recommendation_item');
        // Entity fields are conditions for updates.
        foreach ($conditions as $key => $value) {
          $query->condition($key, $value);
        }
        $query->fields($updates);
        $query->execute();
      }
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      $transaction->rollBack();
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Create or update an item record associated with an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to search for.
   * @param string $status
   *   The sync status of this item record.
   * @param string $remote_id
   *   The Id of this item on the remote system.
   * @param string $remote_type
   *   The type of this item on the remote system.
   *
   * @return bool
   *   True if successful or False otherwise.
   */
  protected function createOrUpdateItemRecord(EntityInterface $entity, $status = NULL, $remote_id = NULL, $remote_type = NULL) {
    $transaction = $this->database->startTransaction();
    try {
      $conditions = $this->getConditionsFromEntity($entity);
      if (!isset($conditions['plugin_id'])) {
        $conditions['plugin_id'] = $this->pluginId;
      }
      $updates = [];
      // Check to see if this item exists.
      if ($this->getItemRecords($conditions)) {
        // Update record.
        $query = $this->database->update('perls_recommendation_item');
        if ($status !== NULL) {
          $updates['status'] = $status;
        }
        // Entity fields are conditions for updates.
        foreach ($conditions as $key => $value) {
          $query->condition($key, $value);
        }
      }
      else {
        // Create record.
        $query = $this->database->insert('perls_recommendation_item');
        $updates['status'] = ($status !== NULL) ? $status : self::STATUS_NEEDS_SYNC;
        // Entity fields are conditions for updates.
        foreach ($conditions as $key => $value) {
          $updates[$key] = $value;
        }
        // Add some default values.
        $updates['remote_id'] = '';
        $updates['remote_type'] = '';
      }
      // Add common fields.
      if ($remote_id) {
        $updates['remote_id'] = $remote_id;
      }
      if ($remote_type) {
        $updates['remote_type'] = $remote_type;
      }
      $query->fields($updates);
      $query->execute();
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      $transaction->rollBack();
      return FALSE;
    }
    $this->database->popTransaction($transaction->name());
    return TRUE;
  }

  /**
   * Create an item record from an array.
   */
  protected function createItemRecord(array $item_record) {
    $transaction = $this->database->startTransaction();
    try {
      if (!isset($item_record['plugin_id'])) {
        $item_record['plugin_id'] = $this->pluginId;
      }
      // Create record.
      $query = $this->database->insert('perls_recommendation_item');
      $query->fields($item_record);
      $query->execute();
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      $transaction->rollBack();
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Delete the Item Records using supplied conditions.
   *
   * @param array $conditions
   *   The array of conditions to add to the select statement.
   *
   * @return array
   *   The number of item records deleted by this query.
   */
  protected function deleteItemRecords(array $conditions = []) {
    $transaction = $this->database->startTransaction();
    try {
      if (!isset($conditions['plugin_id'])) {
        $conditions['plugin_id'] = $this->pluginId;
      }
      $query = $this->database->delete('perls_recommendation_item');
      foreach ($conditions as $key => $value) {
        $query->condition($key, $value);
      }
      return $query->execute();
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      $transaction->rollBack();
      return 0;
    }
  }

  /**
   * Delete the Item Records associated with this entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to search for.
   *
   * @return array
   *   The item records result of this search.
   */
  protected function deleteItemRecordByEntity(EntityInterface $entity) {
    return $this->deleteItemRecords($this->getConditionsFromEntity($entity));
  }

  /**
   * Get or create user recommendation status entity for a given user.
   *
   * @param Drupal\user\UserInterface $user
   *   The user of interest.
   */
  protected function getOrCreateUserRecommendationStatus(UserInterface $user) {
    if (!$user) {
      return;
    }
    $status = $this->entityTypeManager
      ->getStorage('user_recommendation_status')
      ->loadByProperties(['user_id' => $user->id()]);
    if (!empty($status)) {
      // Return saved entity.
      return reset($status);
    }
    return UserRecommendationStatus::create(['user_id' => $user]);
  }

}
