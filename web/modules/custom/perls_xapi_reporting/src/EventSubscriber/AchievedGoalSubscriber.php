<?php

namespace Drupal\perls_xapi_reporting\EventSubscriber;

use Drupal\Component\Datetime\Time;
use Drupal\perls_learner_state\Plugin\XapiStateManager;
use Drupal\perls_goals\Event\AchievedGoalEvent;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber which catch the event when user achieved a goal.
 */
class AchievedGoalSubscriber implements EventSubscriberInterface {

  /**
   * Xapi state manager service.
   *
   * @var \Drupal\perls_learner_state\Plugin\XapiStateManager
   */
  protected $xapiStateManager;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * AchievedGoalSubscriber constructor.
   *
   * @param \Drupal\perls_learner_state\Plugin\XapiStateManager $xapi_state_manager
   *   Xapi statement manager.
   * @param \Drupal\Component\Datetime\Time $time
   *   Drupal time service.
   */
  public function __construct(
    XapiStateManager $xapi_state_manager,
    Time $time) {
    $this->xapiStateManager = $xapi_state_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    if (class_exists(AchievedGoalEvent::class)) {
      $events[AchievedGoalEvent::ACHIEVED_GOAL][] = ['sendXapiStatement'];
    }
    return $events;
  }

  /**
   * Send an xapi statement if a user achieved a personal goal.
   *
   * @param \Drupal\perls_goals\Event\AchievedGoalEvent $event
   *   A drupal event which was triggered when a user achieved a goal.
   */
  public function sendXapiStatement(AchievedGoalEvent $event) {
    /** @var \Drupal\perls_learner_state\Plugin\XapiState\UserAchievedGoal $achieved_goal_xapi */
    $achieved_goal_xapi = $this->xapiStateManager->createInstance('xapi_user_achieved_goal');
    $achieved_goal_xapi->setGoalField($event->getGoalField());
    $achieved_goal_xapi->getReadyStatement(NULL, $this->time->getRequestTime(), User::load($event->getUid()));
    // Achieved goal is calculated during cron run so need to
    // include user to use for statement.
    $achieved_goal_xapi->sendStatement($event->getUid());
  }

}
