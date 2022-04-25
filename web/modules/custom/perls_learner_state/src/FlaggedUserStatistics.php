<?php

namespace Drupal\perls_learner_state;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\perls_core\PerlsCore;

/**
 * Provides statistics for all the users except current user.
 *
 * @package Drupal\perls_learner_state
 */
class FlaggedUserStatistics {

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
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * UserStatistics constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The current database connection.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(Connection $database, AccountInterface $currentUser) {
    $this->database = $database;
    $this->currentUser = $currentUser;
  }

  /**
   * Retrieves stats for all users except current.
   *
   * @return array
   *   An associative array of all users stats.
   */
  public function getFlaggedUserStatistics() {
    return $this->getCompletedLearningObject()
      + $this->getLearningObjectViewedWeekly()
      + $this->getCompletedCourseStatistics()
      + $this->getTotalViewedStatistics()
      + $this->getBookmarkedStatistics()
      + $this->getAverageWeeklyTestResult();
  }

  /**
   * Retrieves flagging stats for all users except current.
   *
   * @param string $flag_type
   *   The flag type.
   * @param string $time_frame
   *   (optional): A time frame for statistics. Accept total, week, month.
   * @param bool|null $lo_stats
   *   (optional): Flag to get the stats only related to learning objects.
   *
   * @return array
   *   An associative array of a particular flagging stats.
   */
  protected function getOtherUsersFlaggingStatistics(string $flag_type, string $time_frame = 'total', bool $lo_stats = FALSE): array {
    $result = [];
    $cache_bin = 'others_flags_' . $time_frame . '_' . $flag_type;
    if (isset($this->queryCache[$cache_bin])) {
      return $this->queryCache[$cache_bin];
    }

    $query = $this->database->select('flagging', 'f')
      ->fields('f', ['flag_id', 'uid'])
      ->condition('f.uid', $this->currentUser->id(), '!=')
      ->condition('f.flag_id', $flag_type)
      ->groupBy('f.uid');

    // Get stats related only to learning objects.
    if ($lo_stats) {
      $query->leftJoin('node', 'n', 'n.nid = f.entity_id');
      $query->condition('n.type', PerlsCore::getLearningObjectList(), 'IN');
    }

    if ($time_frame !== 'total') {
      $this->addTimeFrame($query, 'f.created', $time_frame);
    }

    $query->addExpression('COUNT(flag_id)', 'count');

    $results = $query->execute()->fetchAll();

    $average = $this->getAverage($results);
    if (!empty($average)) {
      $result[$flag_type] = $average;
    }

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
   * @param string $time_frame
   *   (optional): A time frame for statistics. Accept total, week, month.
   *
   * @return string
   *   Number of completed course for a time frame.
   */
  protected function getCompletedCourses(string $time_frame = 'total') {
    $cache_bin = 'others_completed_course_' . $time_frame;
    if (isset($this->queryCache[$cache_bin])) {
      return $this->queryCache[$cache_bin];
    }

    $query = $this->database->select('flagging', 'f')
      ->fields('f', ['flag_id', 'uid'])
      ->condition('f.uid', $this->currentUser->id(), '!=')
      ->condition('f.flag_id', 'completed')
      ->condition('f.entity_type', 'node')
      ->groupBy('f.uid');
    $query->leftJoin('node', 'n', 'n.nid = f.entity_id');
    $query->condition('n.type', 'course');
    if ($time_frame !== 'total') {
      $this->addTimeFrame($query, 'f.created', $time_frame);
    }
    $query->addExpression('COUNT(flag_id)', 'count');
    $results = $query->execute()->fetchAll();

    $average = $this->getAverage($results);
    if (!empty($average)) {
      $this->queryCache[$cache_bin] = $average;
      return $average;
    }
    else {
      return 0;
    }

  }

  /**
   * Retrieves history stats for all users except current.
   *
   * @param string $time_frame
   *   (optional): A time frame for statistics. Accept total, week, month.
   *
   * @return string
   *   History stats.
   */
  protected function getHistoryStatistics(string $time_frame = 'total') {
    $cache_bin = 'others_seen_' . $time_frame;
    if (isset($this->queryCache[$cache_bin])) {
      return $this->queryCache[$cache_bin];
    }

    $query = $this->database->select('history', 'h')
      ->fields('h', ['nid', 'uid'])
      ->condition('h.uid', $this->currentUser->id(), '!=')
      ->groupby('h.uid');

    if ($time_frame !== 'total') {
      $this->addTimeFrame($query, 'timestamp', $time_frame);
    }

    $query->addExpression('COUNT(nid)', 'count');
    $results = $query->execute()->fetchAll();

    $average = $this->getAverage($results);

    $this->queryCache[$cache_bin] = $average;
    return $average;
  }

  /**
   * Helper method to calculate the average.
   *
   * @param array $results
   *   Result query.
   *
   * @return string
   *   Returns average.
   */
  private function getAverage(array $results) {
    $average = 0;

    if (!empty($results)) {
      $totalUserActive = count($results);

      // Total sum.
      $sum = 0;
      foreach ($results as $res) {
        $sum += $res->count;
      }

      // Average.
      $average = $sum / $totalUserActive;
    }

    return $average;
  }

  /**
   * Count the weekly view of learning object for all users except current.
   *
   * @param string $time_frame
   *   Time frame.
   *
   * @return array
   *   The weekly stat of viewed learning objects.
   */
  protected function getLearningObjectViewedWeekly($time_frame = '4 week'): array {
    $query = $this->database->select('node', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', PerlsCore::getLearningObjectList(), 'IN');
    $query->leftJoin('history', 'h', 'n.nid = h.nid');
    $query->fields('h', ['uid']);
    $query->condition('h.uid', $this->currentUser->id(), '!=');
    $query->groupby('h.uid');
    $this->addTimeFrame($query, 'h.timestamp', $time_frame);
    $query->addExpression('COUNT(n.nid)', 'count');
    $results = $query->execute()->fetchAll();

    $average = $this->getAverage($results);
    return ["others_average_seen_count_lo_week" => $average];
  }

  /**
   * Provides statistics about learning objects.
   *
   * @param string $time_frame
   *   Time frame.
   *
   * @return array
   *   A total and weekly statistics about learning objects.
   */
  protected function getCompletedLearningObject($time_frame = '4 week'): array {
    $totalFlagStat = $this->getOtherUsersFlaggingStatistics('completed', 'total', TRUE);
    $weeklyFlagStat = $this->getOtherUsersFlaggingStatistics('completed', $time_frame, TRUE);

    return [
      'others_average_completed_count_total' => $totalFlagStat['completed'],
      'others_average_completed_count_week' => $weeklyFlagStat['completed'],
    ];
  }

  /**
   * Provides statistics about completed course.
   *
   * @param string $time_frame
   *   Time frame.
   *
   * @return array
   *   A total and monthly statistics about completed course.
   */
  protected function getCompletedCourseStatistics($time_frame = '4 month'): array {
    $totalFlagStat = $this->getCompletedCourses();
    $monthlyFlagStat = $this->getCompletedCourses($time_frame);

    return [
      'others_average_completed_count_course_total' => $totalFlagStat,
      'others_average_completed_count_course_month' => $monthlyFlagStat,
    ];
  }

  /**
   * Provides statistics about number of seen items.
   *
   * @return array
   *   Total number of seen items.
   */
  protected function getTotalViewedStatistics(): array {
    $seenStat = $this->getHistoryStatistics();
    return ["others_average_seen_count_total" => $seenStat];
  }

  /**
   * Provides statistics about number of bookmarked items.
   *
   * @param string $time_frame
   *   Time frame.
   *
   * @return array
   *   Number of bookmarked items.
   */
  protected function getBookmarkedStatistics($time_frame = '4 week'): array {
    $allFlaged = $this->getOtherUsersFlaggingStatistics('bookmark', $time_frame, TRUE);
    return ['others_average_bookmarked_count_total' => $allFlaged['bookmark']];
  }

  /**
   * Calculates the average result of users except current for week time frame.
   *
   * @param string $time_frame
   *   Time frame.
   *
   * @return array
   *   The average test result of users except current for a week time frame.
   */
  protected function getAverageWeeklyTestResult($time_frame = '1 week'): array {
    $query = $this->database->select('flagging', 'f')
      ->condition('f.flag_id', 'test_results')
      ->condition('f.uid', $this->currentUser->id(), '!=');
    $this->addTimeFrame($query, 'f.created', $time_frame);
    $query->join('flagging__field_test_attempts', 'ffta', 'ffta.entity_id = f.id');
    $query->join('paragraph__field_test_result', 'pftr', 'pftr.entity_id = ffta.field_test_attempts_target_id');
    $query->addExpression('AVG(field_test_result_value)', 'test_result_avg');
    $avg_test_result = $query->execute()->fetchField();
    if (!empty($avg_test_result)) {
      return ['others_average_result_avg_test_week' => round($avg_test_result * 100, 0)];
    }
    else {
      return ['others_average_result_avg_test_week' => 0];
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
  private function addTimeFrame(SelectInterface &$query, string $field, string $time_frame) {
    if (strpos($time_frame, 'week') !== FALSE ||
      strpos($time_frame, 'month') !== FALSE ||
      strpos($time_frame, 'day') !== FALSE) {
      $timeFrame = strtoupper($time_frame);
      $query->where("{$field} BETWEEN UNIX_TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL - {$timeFrame})) AND UNIX_TIMESTAMP()");
    }
  }

}
