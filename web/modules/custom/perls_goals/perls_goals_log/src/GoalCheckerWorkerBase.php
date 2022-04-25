<?php

namespace Drupal\perls_goals_log;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\perls_goals\Event\AchievedGoalEvent;
use Drupal\perls_goals\GoalCalculator;
use Drupal\perls_goals\GoalHelper;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * This is a base class to help to manage queue worker of user's goals.
 */
class GoalCheckerWorkerBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {
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
   * Goal helper service.
   *
   * @var \Drupal\perls_goals\GoalHelper
   */
  protected $goalHelper;

  /**
   * AchievedGoalCheckerWorker constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\perls_goals\GoalCalculator $goal_calculator
   *   Goal calculator service.
   * @param \Drupal\perls_goals_log\PerlsLogManager $log_manager
   *   Log checker service.
   * @param \Drupal\Component\Datetime\Time $time
   *   Drupal time service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\perls_goals\GoalHelper $goal_helper
   *   A goal helper service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    GoalCalculator $goal_calculator,
    PerlsLogManager $log_manager,
    Time $time,
    EventDispatcherInterface $event_dispatcher,
    EntityTypeManagerInterface $entity_type_manager,
    GoalHelper $goal_helper
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->goalCalculator = $goal_calculator;
    $this->logManager = $log_manager;
    $this->time = $time;
    $this->eventDispatcher = $event_dispatcher;
    $this->fieldConfigManager = $entity_type_manager->getStorage('field_config');
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
      $container->get('perls_goals.goals_calculate'),
      $container->get('perls_goals_log.log_manager'),
      $container->get('datetime.time'),
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
      $container->get('perls_goals.goal_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processQueueItems($uid, array $goal_list) {
    $user = User::load($uid);
    if (!$user) {
      return;
    }
    $achieved_goals = $this->goalCalculator->checkAchievedGoals($user, $goal_list);
    if (empty($achieved_goals)) {
      return;
    }

    // Load existing logs.
    $week_number = date('W', $this->time->getRequestTime());
    $month_number = date('m', $this->time->getRequestTime());
    $weekly_goal_logs = $this->logManager->getLog($uid, array_keys($achieved_goals), 'week', $week_number);
    $monthly_goal_logs = $this->logManager->getLog($uid, array_keys($achieved_goals), 'month', $month_number);

    $logs = array_merge($monthly_goal_logs, $weekly_goal_logs);
    /** @var \Drupal\paragraphs\Entity\Paragraph $logged_field */
    foreach ($logs as $log) {
      if ($log->hasField('field_goal_log_goal_name') &&
      !empty($log->get('field_goal_log_goal_name')->getString()) &&
      isset($achieved_goals[$log->get('field_goal_log_goal_name')->getString()])) {
        unset($achieved_goals[$log->get('field_goal_log_goal_name')->getString()]);
      }
    }

    if (empty($achieved_goals)) {
      return;
    }

    foreach ($achieved_goals as $goal) {
      $event = new AchievedGoalEvent();
      $event->setGoalField($goal['api_field']);
      $event->setUid($user->id());
      $this->eventDispatcher->dispatch(AchievedGoalEvent::ACHIEVED_GOAL, $event);
    }
  }

  /**
   * Mandatory queue worker function.
   *
   * @param mixed $data
   *   It should contains two keys:
   *    'user': A drupal user id who has goals,
   *    'goal_type': 'This a time frame or a goal value type(integer or avg)'.
   */
  public function processItem($data) {}

}
