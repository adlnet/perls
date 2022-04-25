<?php

namespace Drupal\perls_api;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\perls_learner_state\FlaggedUserStatistics;

/**
 * Provides statistics for current user.
 *
 * @package Drupal\perls_api
 */
class UserStatistics {

  /**
   * This variable store the result of sql queries, it works as static cache.
   *
   * @var array
   */
  protected $queryCache = [];

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Provides statistics for all the users except current user.
   *
   * @var \Drupal\perls_learner_state\FlaggedUserStatistics
   */
  protected $flaggedUserStatistics;

  /**
   * Provides learning object machine names.
   *
   * @var \Drupal\perls_api\PerlsHelper
   */
  private $perlsHelper;

  /**
   * UserStatistics constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The current database connection.
   * @param \Drupal\perls_api\PerlsHelper $perlsHelper
   *   Helper service that provides learning object machine names.
   * @param \Drupal\perls_learner_state\FlaggedUserStatistics $flaggedUserStatistics
   *   Provides statistics for all the users except current user.
   */
  public function __construct(Connection $database, PerlsHelper $perlsHelper, FlaggedUserStatistics $flaggedUserStatistics) {
    $this->database = $database;
    $this->perlsHelper = $perlsHelper;
    $this->flaggedUserStatistics = $flaggedUserStatistics;
  }

  /**
   * Retrieves stats for the specified user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   *
   * @return array
   *   An associative array of user stats.
   */
  public function getUserStatistics(AccountInterface $account) {
    return $this->getCompletedLearningObject($account)
      + $this->getLearningObjectViewedWeekly($account)
      + $this->getCompletedCourseStatistics($account)
      + $this->getTotalViewedStatistics($account)
      + $this->getBookmarkedStatistics($account)
      + $this->getAverageWeeklyTestResult($account)
      + $this->flaggedUserStatistics->getFlaggedUserStatistics();
  }

  /**
   * Retrieves flagging stats for the specified user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   * @param string $time_frame
   *   (optional): A time frame for statistics. Accept total, week, month.
   * @param bool|null $lo_stats
   *   (optional): Flag to get the stats only related to learning objects.
   *
   * @return array
   *   An associative array of flagging stats.
   */
  protected function getFlaggingStatistics(AccountInterface $account, string $time_frame = 'total', bool $lo_stats = FALSE) {
    $cache_bin = 'flags_' . $time_frame . '_' . $account->id();
    if (isset($this->queryCache[$cache_bin])) {
      return $this->queryCache[$cache_bin];
    }

    $query = $this->database->select('flagging', 'f')
      ->fields('f', ['flag_id'])
      ->condition('f.uid', $account->id())
      ->groupBy('f.flag_id');

    // Get stats related only to learning objects.
    if ($lo_stats) {
      $query->leftJoin('node', 'n', 'n.nid = f.entity_id');
      $query->condition('n.type', $this->perlsHelper->getLearningObjectList(), 'IN');
    }

    if ($time_frame !== 'total') {
      $this->addTimeFrame($query, 'f.created', $time_frame);
    }

    $query->addExpression('COUNT(*)', 'count');

    $result = $query->execute()->fetchAllKeyed();

    // Set default values in case there was no data for one of the flags.
    $result += [
      'bookmark' => 0,
      'completed' => 0,
      'started_course' => 0,
    ];

    $this->queryCache[$cache_bin] = $result;
    return $result;
  }

  /**
   * Count the completed courses in a timeframe.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A drupal user.
   * @param string $time_frame
   *   (optional): A time frame for statistics. Accept total, week, month.
   *
   * @return string
   *   Number of completed course for a time frame.
   */
  protected function getCompletedCourses(AccountInterface $account, $time_frame = 'total') {
    $cache_bin = 'completed_course_' . $time_frame . '_' . $account->id();
    if (isset($this->queryCache[$cache_bin])) {
      return $this->queryCache[$cache_bin];
    }

    $query = $this->database->select('flagging', 'f')
      ->fields('f', ['flag_id'])
      ->condition('f.uid', $account->id())
      ->condition('f.flag_id', 'completed')
      ->condition('f.entity_type', 'node')
      ->groupBy('f.flag_id');
    $query->leftJoin('node', 'n', 'n.nid = f.entity_id');
    $query->condition('n.type', 'course');
    if ($time_frame !== 'total') {
      $this->addTimeFrame($query, 'f.created', $time_frame);
    }
    $query->addExpression('COUNT(*)', 'count');
    $result = $query->execute()->fetchAllKeyed();
    if (!empty($result)) {
      $this->queryCache[$cache_bin] = $result['completed'];
      return $result['completed'];
    }
    else {
      return 0;
    }

  }

  /**
   * Retrieves history stats for the specified user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   * @param string $time_frame
   *   (optional): A time frame for statistics. Accept total, week, month.
   * @param bool|null $lo_stats
   *   (optional): Flag to get the stats only related to learning objects.
   *
   * @return array
   *   An associative array of history stats.
   */
  protected function getHistoryStatistics(AccountInterface $account, string $time_frame = 'total', bool $lo_stats = FALSE) {
    $cache_bin = 'seen_' . $time_frame . '_' . $account->id();
    if (isset($this->queryCache[$cache_bin])) {
      return $this->queryCache[$cache_bin];
    }

    $query = $this->database->select('history', 'h')
      ->condition('h.uid', $account->id());

    if ($time_frame !== 'total') {
      $this->addTimeFrame($query, 'timestamp', $time_frame);
    }

    // Get stats related only to learning objects.
    if ($lo_stats) {
      $query->leftJoin('node', 'n', 'n.nid = h.nid');
      $query->condition('n.type', $this->perlsHelper->getLearningObjectList(), 'IN');
    }

    $query->addExpression('COUNT(*)', 'count');
    $seen = $query->execute()->fetchField();
    $this->queryCache[$cache_bin] = $seen;
    return $seen;
  }

  /**
   * Count the weekly view of learning object for a user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A drupal account.
   *
   * @return int[]
   *   The weekly stat of viewed learning objects.
   */
  protected function getLearningObjectViewedWeekly(AccountInterface $account) {
    $query = $this->database->select('node', 'n')
      ->condition('n.type', $this->perlsHelper->getLearningObjectList(), 'IN');
    $query->leftJoin('history', 'h', 'n.nid = h.nid');
    $query->condition('h.uid', $account->id());
    $this->addTimeFrame($query, 'h.timestamp', 'week');
    $query->addExpression('COUNT(*)', 'count');
    $seen = $query->execute()->fetchField();
    return ["seen_count_lo_week" => (int) $seen];
  }

  /**
   * Provides statistics about learning objects.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A drupal account.
   *
   * @return int[]
   *   A total and weekly statistics about learning objects.
   */
  protected function getCompletedLearningObject(AccountInterface $account) {
    $total_flag_stat = $this->getFlaggingStatistics($account, 'total', TRUE);
    $weekly_flag_stat = $this->getFlaggingStatistics($account, 'week', TRUE);

    return [
      'completed_count_total' => (int) $total_flag_stat['completed'],
      'completed_count_week' => (int) $weekly_flag_stat['completed'],
    ];
  }

  /**
   * Provides statistics about completed course.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A drupal account.
   *
   * @return int[]
   *   A total and monthly statistics about completed course.
   */
  protected function getCompletedCourseStatistics(AccountInterface $account) {
    $total_flag_stat = $this->getCompletedCourses($account);
    $monthly_flag_stat = $this->getCompletedCourses($account, 'month');

    return [
      'completed_count_course_total' => (int) $total_flag_stat,
      'completed_count_course_month' => (int) $monthly_flag_stat,
    ];

  }

  /**
   * Provides statistics about number of seen items.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A drupal user.
   *
   * @return int[]
   *   Total number of seen items.
   */
  protected function getTotalViewedStatistics(AccountInterface $account) {
    $seen_stat = $this->getHistoryStatistics($account, 'total', TRUE);
    return ["seen_count_total" => (int) $seen_stat];
  }

  /**
   * Provides statistics about number of bookmarked items.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A drupal account.
   *
   * @return int[]
   *   Number of bookmarked items.
   */
  protected function getBookmarkedStatistics(AccountInterface $account) {
    $all_flaged = $this->getFlaggingStatistics($account, 'total', TRUE);
    return ['bookmarked_count_total' => (int) $all_flaged['bookmark']];
  }

  /**
   * Calculates the average test result of a user in a week time frame.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A drupal user account.
   *
   * @return array|int[]
   *   The average test result of a user for a week time frame.
   */
  protected function getAverageWeeklyTestResult(AccountInterface $account) {
    $query = $this->database->select('flagging', 'f')
      ->condition('f.flag_id', 'test_results')
      ->condition('f.uid', $account->id());
    $this->addTimeFrame($query, 'f.created', 'week');
    $query->join('flagging__field_test_attempts', 'ffta', 'ffta.entity_id = f.id');
    $query->join('paragraph__field_test_result', 'pftr', 'pftr.entity_id = ffta.field_test_attempts_target_id');
    $query->addExpression('AVG(field_test_result_value)', 'test_result_avg');
    $avg_test_result = $query->execute()->fetchField();
    if (!empty($avg_test_result)) {
      return ['result_avg_test_week' => round($avg_test_result * 100, 0)];
    }
    else {
      return ['result_avg_test_week' => 0];
    }
  }

  /**
   * Add week and month time frame condition to query.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   A drupal select query.
   * @param string $field
   *   The name of table field which contains the timestamp.
   * @param string $time_frame
   *   Currently it support two values, week and month.
   */
  private function addTimeFrame(SelectInterface &$query, $field, $time_frame) {
    switch ($time_frame) {
      case 'week':
        $query->where("{$field} BETWEEN UNIX_TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL - WEEKDAY(CURDATE()) DAY)) AND UNIX_TIMESTAMP()");
        break;

      case 'month':
        $query->where("{$field} BETWEEN UNIX_TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL - DAYOFMONTH(CURDATE()) DAY)) AND UNIX_TIMESTAMP()");
        break;
    }
  }

}
