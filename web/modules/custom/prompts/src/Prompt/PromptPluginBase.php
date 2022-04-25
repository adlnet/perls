<?php

namespace Drupal\prompts\Prompt;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\user\UserInterface;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * An abstract class what every prompt should implement.
 */
abstract class PromptPluginBase extends PluginBase implements PromptTypeInterface, ContainerFactoryPluginInterface {

  /**
   * This value we will use the sql query to retrieves data.
   *
   * @var int
   */
  protected $timePeriod = 24;

  /**
   * Drupal state api.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Drupal entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Debug flag for showing prompts always.
   *
   * @var bool
   */
  protected $debug = FALSE;

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   Drupal database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Drupal entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;

    if (\Drupal::moduleHandler()->moduleExists('prompts_debug')) {
      $this->debug = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->get('label');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->get('description');
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->get('id');
  }

  /**
   * {@inheritdoc}
   */
  public function getWebformId() {
    return $this->get('webform');
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeLimit() {
    return $this->get('limit');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestionField() {
    return $this->get('questionField');
  }

  /**
   * {@inheritdoc}
   */
  public function isTimeToAsk(UserInterface $user) {
    $is_time = &drupal_static(__FUNCTION__);
    $webform_ID = $this->getWebformId();
    if (!isset($is_time) || !isset($is_time[$webform_ID])) {
      $start_date = strtotime(sprintf('-%d hours', $this->timePeriod));
      $end_date = time();
      $query = $this->entityTypeManager->getStorage('webform_submission')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('webform_id', $webform_ID, '=')
        ->condition('uid', $user->id(), '=')
        ->condition('created', [$start_date, $end_date], 'BETWEEN');
      $group = $query->orConditionGroup()
        ->condition('locked', '1', '=')
        ->condition('completed', NULL, 'IS NOT NULL');
      $query->condition($group);
      $query->sort('sid', 'DESC');
      $query->range(0, 1);
      $is_time[$webform_ID] = !(bool) $query->execute();
    }
    return $is_time[$webform_ID];
  }

  /**
   * {@inheritdoc}
   */
  public function getUserQuestions(UserInterface $user) {}

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    if (!empty($this->pluginDefinition[$key])) {
      return $this->pluginDefinition[$key];
    }
  }

  /**
   * Loads previously cretaed submissions in a time frame.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   * @param string $limit
   *   A time period between now, minus the hours in the variable.
   *
   * @return array
   *   The loaded submissions.
   */
  protected function getPreGeneratedQuestions(UserInterface $user, $limit = NULL) {
    if (empty($limit)) {
      $limit = $this->timePeriod;
    }
    $query = $this->entityTypeManager->getStorage('webform_submission')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('webform_id', $this->getWebformId(), '=')
      ->condition('uid', $user->id(), '=')
      ->condition('completed', NULL, 'IS NULL')
      ->condition('locked', 0, '=')
      ->condition('in_draft', 1, '=');
    if ($limit != 'all') {
      $start_date = strtotime(sprintf('-%d hours', $limit));
      $end_date = time();
      $query->condition('created', [$start_date, $end_date], 'BETWEEN');
    }

    $sids = $query->execute();
    return WebformSubmission::loadMultiple($sids);
  }

  /**
   * {@inheritdoc}
   */
  public function generateNewQuestion(EntityInterface $source_entity, $uid) {

    if ($this->submissionAlreadyExists($source_entity, $uid)) {
      return NULL;
    }

    $submission = WebformSubmission::create([
      'in_draft' => 1,
      'uid' => $uid,
      'webform_id' => $this->getWebformId(),
      'entity_type' => $source_entity->getEntityTypeId(),
      'entity_id' => $source_entity->id(),
    ]);
    $submission->save();
    return $submission;
  }

  /**
   * Check for any existing submission.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   Entity the prompt is regarding.
   * @param int $uid
   *   A drupal user.
   *
   * @return bool
   *   Does a submission alrady exist for this user/webform/entity combo.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function submissionAlreadyExists(EntityInterface $source_entity, $uid) {
    $bundle = $source_entity->getEntityTypeId();
    $query = $this->entityTypeManager->getStorage('webform_submission')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('webform_id', $this->getWebformId(), '=')
      ->condition('uid', $uid, '=')
      ->condition('entity_id', $source_entity->id(), '=')
      ->condition('entity_type', $bundle, '=');
    $query->range(0, 1);

    $result = $query->execute();
    return (bool) $result;

  }

}
