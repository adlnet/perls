<?php

namespace Drupal\perls_recommendation;

use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\user\UserInterface;
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
abstract class RecommendationEnginePluginBase extends PluginBase implements RecommendationEnginePluginInterface, ContainerFactoryPluginInterface {

  /**
   * The logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('Recommendation Engine Plugin')
    );
  }

  /**
   * Constructor for Recommendation Engine.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerInterface $logger_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger_factory;
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
  public function buildConfigurationForm(array &$form, FormStateInterface $form_state, Config $config) {
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
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state, Config $config) {
  }

  /**
   * {@inheritdoc}
   */
  public function queueUserForRecommendations(UserInterface $user) {
  }

  /**
   * {@inheritdoc}
   */
  public function getUserRecommendations(UserInterface $user, $count = 5, $now = FALSE) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function userRecommendationsReady(UserInterface $user) {
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

}
