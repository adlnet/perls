<?php

namespace Drupal\notifications_goals;

use Drupal\Core\Database\Connection;
use Drupal\perls_goals\GoalCalculator;

/**
 * This class help to manage push notification for user goals.
 */
class UserGoalNotificationHelper {

  /**
   * Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Help to calculates user goals.
   *
   * @var \Drupal\perls_goals\GoalCalculator
   */
  protected $goalCalculator;

  /**
   * UserGoalNotificationHelper constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Drupal database connection.
   * @param \Drupal\perls_goals\GoalCalculator $goal_calculator
   *   Help to check user goals.
   */
  public function __construct(
    Connection $database,
    GoalCalculator $goal_calculator) {
    $this->database = $database;
    $this->goalCalculator = $goal_calculator;
  }

  /**
   * List of user who we needs to notify.
   *
   * @param int $start_time
   *   The started time in seconds since midnight.
   * @param int $end_time
   *   The end time in seconds since midnight.
   * @param string $day
   *   Name of week day. User wants to get notification this day.
   *
   * @return array|null
   *   The list of user who we need to notify otherwise NULL.
   */
  public function getUserList($start_time, $end_time, $day) {
    $user_list = $this->getNoticeRequests($start_time, $end_time, $day);
    if (empty($user_list)) {
      return NULL;
    }
    $without_goal = $this->goalCalculator->getUserWithoutGoals();
    $user_list = array_diff($user_list, $without_goal);
    return $user_list;
  }

  /**
   * Collects those users who want to get notification now.
   *
   * @param int $start_time
   *   Second since midnight. Filter out those user who wants notification after
   *   this time.
   * @param int $end_time
   *   Second since midnight. Filter out those user who wants notification
   *   before this time.
   * @param string $day
   *   Name of week day. User wants to get notification this day.
   *
   * @return array
   *   List of uids who wants to get notification.
   */
  protected function getNoticeRequests($start_time, $end_time, $day = NULL) {
    $query = $this->database->select('users', 'u')
      ->distinct()
      ->fields('u', ['uid']);
    $query->leftJoin('user__field_notification_time', 'ufnt', 'u.uid = ufnt.entity_id');
    $query->condition('ufnt.bundle', 'user');
    $query->condition('ufnt.field_notification_time_value', [
      $start_time,
      $end_time,
    ], 'BETWEEN');
    $query->leftJoin('user__field_notification_days', 'ufnd', 'u.uid = ufnd.entity_id');
    if ($day) {
      $query->condition('ufnd.bundle', 'user');
      $query->condition('ufnd.field_notification_days_value', $day);
    }
    return $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
  }

}
