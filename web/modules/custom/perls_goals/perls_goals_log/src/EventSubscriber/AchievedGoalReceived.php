<?php

namespace Drupal\perls_goals_log\EventSubscriber;

use Drupal\Component\Datetime\Time;
use Drupal\perls_goals\Event\AchievedGoalEvent;
use Drupal\perls_goals\GoalHelper;
use Drupal\perls_goals_log\PerlsLogManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Catch the event when a user achieved a goal.
 */
class AchievedGoalReceived implements EventSubscriberInterface {

  /**
   * Goal log manager service.
   *
   * @var \Drupal\perls_goals_log\PerlsLogManager
   */
  protected $logManager;

  /**
   * Goal helper service.
   *
   * @var \Drupal\perls_goals\GoalHelper
   */
  private $goalHelper;

  /**
   * Drupal time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * AchievedGoalReceived constructor.
   *
   * @param \Drupal\perls_goals_log\PerlsLogManager $log_manager
   *   A log manager service.
   * @param \Drupal\perls_goals\GoalHelper $goal_helper
   *   A log helper service.
   * @param \Drupal\Component\Datetime\Time $time
   *   Drupal time service.
   */
  public function __construct(PerlsLogManager $log_manager, GoalHelper $goal_helper, Time $time) {
    $this->logManager = $log_manager;
    $this->goalHelper = $goal_helper;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    if (class_exists(AchievedGoalEvent::class)) {
      $events[AchievedGoalEvent::ACHIEVED_GOAL][] = ['saveGoalLog'];
    }
    return $events;
  }

  /**
   * Save the goal log.
   *
   * @param \Drupal\perls_goals\Event\AchievedGoalEvent $event
   *   An event which was triggered when user achieved a goal.
   */
  public function saveGoalLog(AchievedGoalEvent $event) {
    $goal_field_values = $this->goalHelper->getGoalFieldData($event->getGoalField());
    $log_values = [
      'field_goal_log_goal_name' => $event->getGoalField(),
      'field_goal_log_timeframe' => $goal_field_values['time_frame'],
    ];

    if ($goal_field_values['time_frame'] === 'week') {
      $log_values['field_goal_log_time_indicator'] = date('W', $this->time->getRequestTime());
    }
    elseif ($goal_field_values['time_frame'] === 'month') {
      $log_values['field_goal_log_time_indicator'] = date('m', $this->time->getRequestTime());
    }
    $this->logManager->setLog($log_values, $event->getUid());
  }

}
