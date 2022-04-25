<?php

namespace Drupal\perls_learner_state\Plugin;

use Drupal\perls_learner_state\PerlsLearnerStatementFlag;
use Drupal\user\UserInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\perls_goals\GoalHelper;
use Drupal\perls_xapi_reporting\PerlsXapiActivityType;
use Drupal\xapi\LRSRequestGenerator;
use Drupal\xapi\XapiActorIFIManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\xapi\XapiActivityProviderInterface;

/**
 * Base class for user goal xapi statets.
 */
class XapiUserGoalBase extends XapiStateBase {


  /**
   * Goal helper service.
   *
   * @var \Drupal\perls_goals\GoalHelper
   */
  protected $goalHelper;

  /**
   * Constructs a goal state plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\xapi\XapiActorIFIManager $ifi_manager
   *   The actor IFI manager.
   * @param \Drupal\xapi\XapiActivityProviderInterface $activity_provider
   *   The actor provider.
   * @param \Drupal\perls_learner_state\PerlsLearnerStatementFlag $flag_statement_helper
   *   A helper service to manage sync between statement and flags.
   * @param \Drupal\xapi\LRSRequestGenerator $request_generator
   *   The request generator service.
   * @param \Drupal\perls_goals\GoalHelper $goal_helper
   *   The goal helper service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AccountInterface $current_user,
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
    XapiActorIFIManager $ifi_manager,
    XapiActivityProviderInterface $activity_provider,
    PerlsLearnerStatementFlag $flag_statement_helper,
    LRSRequestGenerator $request_generator,
    GoalHelper $goal_helper
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $current_user, $config_factory, $module_handler, $ifi_manager, $activity_provider, $flag_statement_helper, $request_generator);
    $this->goalHelper = $goal_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('plugin.manager.xapi_actor_ifi'),
      $container->get('xapi.activity_provider'),
      $container->get('perls_learner_state.flagging_helper'),
      $container->get('lrs.request_generator'),
      $container->get('perls_goals.goal_helper')
    );
  }

  /**
   * Store the name of drupal goal field.
   *
   * @var string
   */
  protected $goalField = '';

  /**
   * Give back the name of goal field.
   *
   * @return string
   *   The goal field name.
   */
  public function getGoalField(): string {
    return $this->goalField;
  }

  /**
   * Sets the $fieldName property.
   *
   * @param string $goalField
   *   The name of the drupal goal field.
   */
  public function setGoalField($goalField) {
    $this->goalField = $goalField;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareStatement(int $timestamp = NULL, UserInterface $user = NULL) {
    parent::prepareStatement($timestamp, $user);

    // Override the object definition.
    $this->statement->getObject()
      ->setRelativeId($this->getGoalId())
      ->setName($this->getGoalName())
      ->setType(PerlsXapiActivityType::GOAL);
  }

  /**
   * Retrieves a relative IRI for the current goal.
   *
   * @return string
   *   The relative IRI for the goal.
   */
  protected function getGoalId(): string {
    /** @var \Drupal\field\Entity\FieldConfig $goal_field_config */
    $goal_field_config = $this->goalHelper->loadGoalField($this->getGoalField());
    return 'goal#' . $goal_field_config->getName();
  }

  /**
   * Gets the activity name for the current goal.
   *
   * @return string
   *   The activity name.
   */
  protected function getGoalName(): string {
    /** @var \Drupal\field\Entity\FieldConfig $goal_field_config */
    $goal_field_config = $this->goalHelper->loadGoalField($this->getGoalField());
    return 'a goal of ' . strtolower($goal_field_config->getLabel());
  }

}
