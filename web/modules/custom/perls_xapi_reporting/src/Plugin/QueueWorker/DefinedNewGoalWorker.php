<?php

namespace Drupal\perls_xapi_reporting\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\perls_learner_state\Plugin\XapiStateManager;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Use cron to send xapi statement about new defined goals.
 *
 * @QueueWorker(
 *   id = "xapi_send_defined_goal",
 *   title = @Translation("New defined goal"),
 *   cron = {"time" = 30}
 * )
 */
class DefinedNewGoalWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Goal calculator service.
   *
   * @var \Drupal\perls_goals\GoalCalculator
   */
  protected $goalCalculator;

  /**
   * User goal log checker service.
   *
   * @var \Drupal\perls_goals_log\PerlsLogManager
   */
  protected $logManager;

  /**
   * Drupal time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $eventDispatcher;

  /**
   * Field config manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fieldConfigManager;

  /**
   * Xapi statement manager service.
   *
   * @var \Drupal\perls_learner_state\Plugin\XapiStateManager
   */
  protected $statementManager;

  /**
   * DefinedNewGoalWorker constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\perls_learner_state\Plugin\XapiStateManager $statement_manager
   *   Xapi statement manager service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    XapiStateManager $statement_manager,
    TimeInterface $time
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fieldConfigManager = $entity_type_manager->getStorage('field_config');
    $this->statementManager = $statement_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.state_manager'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (empty($data['user'])) {
      return;
    }

    $user = User::load($data['user']);
    if (!$user) {
      return;
    }

    $field_name = $data['field'];
    /** @var \Drupal\perls_learner_state\Plugin\XapiState\UserDefineNewGoal $defined_goal_plugin */
    $defined_goal_plugin = $this->statementManager->createInstance('xapi_define_goal');
    if (!empty($field_name)) {
      $defined_goal_plugin->setGoalField($field_name);
      $defined_goal_plugin->setNewGoalValue((int) $data['new_value']);
      $defined_goal_plugin->getReadyStatement(NULL, $this->time->getRequestMicroTime(), $user);
      $defined_goal_plugin->sendStatement((int) $user->id());
    }
  }

}
