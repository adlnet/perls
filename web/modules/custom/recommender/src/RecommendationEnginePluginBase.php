<?php

namespace Drupal\recommender;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBaseTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\recommender\Entity\RecommendationPluginScore;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an base class for Recommendation Engine plugins.
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
 * @RecommendationEnginePlugin(
 *   id = "my_recommendation_engine",
 *   label = @Translation("My Recommendation Engine"),
 *   description = @Translation("Uses my super recommendation engine to recommend content.")
 * )
 * @endcode
 *
 * @see \Drupal\recommender\Annotation\RecommendationEngine
 * @see \Drupal\recommender\RecommendationEnginePluginManager
 * @see \Drupal\recommender\RecommendationEnginePluginInterface
 * @see plugin_api
 */
abstract class RecommendationEnginePluginBase extends PluginBase implements RecommendationEnginePluginInterface, ContainerFactoryPluginInterface {
  use ConfigFormBaseTrait;
  use TranslatedConfigTrait;

  /**
   * Default recommendation reason.
   */
  const DEFAULT_RECOMMENDATION_REASON = '';

  /**
   * The logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The config Factory Interface.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

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
      $container->get('logger.factory')->get('Recommendation Engine Plugin - ' . $plugin_id),
      $container->get('config.factory'),
      $container->get('language_manager'),
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
    ConfigFactory $config_factory,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger_factory;
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfig();
    $reason = $config->get('recommendation_reason');
    $form['number_candidates_generated'] = [
      '#type' => 'number',
      '#step' => 1,
      '#min' => 0,
      '#title' => $this->t('Number of Recommendations'),
      '#description' => $this->t('How many recommendations would you like this plugin to add to the candidates list'),
      '#default_value' => ($config->get('number_candidates_generated')) ?: 5,
    ];
    $form['recommendation_reason'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recommendation Reason'),
      '#description' => $this->t('The reason given to users when this plugin recommends content'),
      '#default_value' => ($reason) ? $reason : $this::DEFAULT_RECOMMENDATION_REASON,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $config = $this->getConfig();
    $values = $form_state->getValues();

    if (isset($values['recommendation_reason'])) {
      $config->set('recommendation_reason', $values['recommendation_reason']);
    }

    if (isset($values['number_candidates_generated'])) {
      $config->set('number_candidates_generated', $values['number_candidates_generated']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function queueUserForRecommendations(AccountInterface $user) {
  }

  /**
   * {@inheritdoc}
   */
  public function getUserRecommendations(AccountInterface $user, $count = 5, $now = FALSE) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function userRecommendationsReady(AccountInterface $user) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateEntity(EntityInterface $entity, $use_queue = FALSE, $delete_entity = FALSE) {
  }

  /**
   * {@inheritdoc}
   */
  public function requiresUpdateFromEntity(EntityInterface $entity) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function resetGraph() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function syncGraph($batch_size = 100) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function supportsStage($stage_id) {
    return isset($this->pluginDefinition['stages'][$stage_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight($stage_id = NULL) {
    if (!$stage_id) {
      return 0;
    }
    $config = $this->getConfig();
    return ($config->get('weight.[' . $stage_id . ']')) ?: $this->pluginDefinition['stages'][$stage_id];
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($stage_id, $weight) {
    $config = $this->getConfig();
    $config->set('weight.[' . $stage_id . ']', $weight);
    $config->save();
  }

  /**
   * {@inheritdoc}
   */
  public function generateCandidates(AccountInterface $user) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function scoreCandidates(array $candidates, AccountInterface $user) {
  }

  /**
   * {@inheritdoc}
   */
  public function alterCandidates(array $candidates, AccountInterface $user) {
    return $candidates;
  }

  /**
   * {@inheritdoc}
   */
  public function rerankCandidates(array $candidates, AccountInterface $user) {
    return $candidates;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['recommender.plugin.' . $this->pluginId . '.settings'];
  }

  /**
   * Get this plugins config settings.
   */
  protected function getConfig() {
    return $this->config('recommender.plugin.' . $this->pluginId . '.settings');
  }

  /**
   * Get Recommendation Reason.
   */
  protected function getRecommendationReason($langcode = NULL) {
    $reason = $this->translatedConfigOrDefault('recommender.plugin.' . $this->pluginId . '.settings', 'recommendation_reason', $langcode);
    return ($reason) ? $reason : $this::DEFAULT_RECOMMENDATION_REASON;
  }

  /**
   * Get number of recommendations to create.
   */
  protected function getNumberOfCandidates() {
    $config = $this->getConfig();
    return ($config->get('number_candidates_generated')) ?: 5;
  }

  /**
   * Update or Create recommendation score entity.
   */
  protected function updateOrCreateScoreEntity(AccountInterface $user, $node_id, $score, $status = RecommendationPluginScore::STATUS_READY) {

    $entity = $this->entityTypeManager
      ->getStorage('recommendation_plugin_score')
      ->loadByProperties(
        [
          'user_id' => $user->id(),
          'nid' => $node_id,
          'plugin_id' => $this->getPluginId(),
        ]
      );

    if (!empty($entity)) {
      $entity = reset($entity);
      $entity->recommendation_reason = $this->getRecommendationReason($user->getPreferredLangcode());
      $entity->score = $score;
      $entity->status = $status;
      $entity->save();
      // Return saved entity.
      return $entity;
    }
    // If it didn't exist create it now.
    $entity = RecommendationPluginScore::create(
      [
        'user_id' => $user->id(),
        'nid' => $node_id,
        'status' => $status,
        'plugin_id' => $this->pluginId,
        'recommendation_reason' => $this->getRecommendationReason($user->getPreferredLangcode()),
        'score' => $score,
      ]
    );
    $entity->save();
    return $entity;
  }


  /**
   * Get Previously Stored score entities.
   */
  protected function getScoreEntity($user_id, $node_id) {
    $entity = $this->entityTypeManager
      ->getStorage('recommendation_plugin_score')
      ->loadByProperties(
        [
          'user_id' => $user_id,
          'nid' => $node_id,
          'plugin_id' => $this->getPluginId(),
          'status' => RecommendationPluginScore::STATUS_PROCESSING,
        ]
      );

    if (!empty($entity)) {
      $entity = reset($entity);
      $entity->setStatus(RecommendationPluginScore::STATUS_READY);
      $entity->save();
      return $entity;
    }
    else {
      return FALSE;
    }
  }


}
