<?php

namespace Drupal\perls_content_management;

use Drupal\Core\Database\Connection;

/**
 * Helper class for node statistics.
 *
 * @package Drupal\perls_content_management
 */
class NodeStatistics {

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * NodeStatistics constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Provides content seen statistics for a node.
   *
   * @param int $node_id
   *   The node id where you want to see the seen statistics.
   * @param string $date_range
   *   The range statistics which can today, week, month, all.
   *
   * @return int
   *   The record number in a time frame.
   */
  public function seenContentStatistics($node_id, $date_range = 'all') {
    $query = $this->database->select('history', 'h')
      ->fields('h', [])
      ->condition('h.nid', $node_id);

    if ($date_range === 'today') {
      $first_day = strtotime('today midnight');
      $last_day = strtotime('tomorrow');
    }
    elseif ($date_range === 'week') {
      $first_day = strtotime('monday this week');
      $last_day = strtotime('monday next week');

    }
    elseif ($date_range === 'month') {
      $first_day = strtotime('first day of this month 00:00:00');
      $last_day = strtotime('first day of next month 00:00:00');
    }
    elseif ($date_range === 'year') {
      $first_day = strtotime('first day of this year 00:00:00');
      $last_day = strtotime('first day of next year 00:00:00');
    }

    if (isset($first_day) && isset($last_day)) {
      $query->condition('h.timestamp', $first_day, '>=');
      $query->condition('h.timestamp', $last_day, '<');
    }

    return $num_rows = $query->countQuery()->execute()->fetchField();
  }

  /**
   * Provides number of flagged content statistics for a node in a time frame.
   *
   * @param int $node_id
   *   The node id where you want to see the seen statistics.
   * @param string $flag_name
   *   The id of flag.
   * @param string $date_range
   *   The range statistics which can today, week, month, year, all.
   *
   * @return int
   *   The record number in a time frame.
   */
  public function flaggedContentStatistics($node_id, $flag_name, $date_range = 'all') {
    $query = $this->database->select('flagging', 'f')
      ->fields('f', [])
      ->condition('f.entity_type', 'node')
      ->condition('f.entity_id', $node_id)
      ->condition('f.flag_id', $flag_name);

    if ($date_range === 'today') {
      $first_day = strtotime('today midnight');
      $last_day = strtotime('tomorrow');
    }
    elseif ($date_range === 'week') {
      $first_day = strtotime('monday this week');
      $last_day = strtotime('monday next week');

    }
    elseif ($date_range === 'month') {
      $first_day = strtotime('first day of this month 00:00:00');
      $last_day = strtotime('first day of next month 00:00:00');
    }
    elseif ($date_range === 'year') {
      $first_day = strtotime('first day of this year 00:00:00');
      $last_day = strtotime('first day of next year 00:00:00');
    }

    if (isset($first_day) && isset($last_day)) {
      $query->condition('f.created', $first_day, '>=');
      $query->condition('f.created', $last_day, '<');
    }

    return $num_rows = $query->countQuery()->execute()->fetchField();
  }

  /**
   * Provides number of webform submissions for a node in a time frame.
   *
   * @param int $node_id
   *   The node id where you want to see the seen statistics.
   * @param string $webform
   *   The id of webform.
   * @param string $date_range
   *   The range statistics which can today, week, month, year, all.
   *
   * @return int
   *   The record number in a time frame.
   */
  public function webformSubmissionCountStatistics($node_id, $webform, $date_range = 'all') {
    $query = $this->database->select('webform_submission', 'ws')
      ->fields('ws', [])
      ->condition('ws.entity_type', 'node')
      ->condition('ws.entity_id', $node_id)
      ->condition('ws.webform_id', $webform);

    if ($date_range === 'today') {
      $first_day = strtotime('today midnight');
      $last_day = strtotime('tomorrow');
    }
    elseif ($date_range === 'week') {
      $first_day = strtotime('monday this week');
      $last_day = strtotime('monday next week');

    }
    elseif ($date_range === 'month') {
      $first_day = strtotime('first day of this month 00:00:00');
      $last_day = strtotime('first day of next month 00:00:00');
    }
    elseif ($date_range === 'year') {
      $first_day = strtotime('first day of this year 00:00:00');
      $last_day = strtotime('first day of next year 00:00:00');
    }

    if (isset($first_day) && isset($last_day)) {
      $query->condition('ws.created', $first_day, '>=');
      $query->condition('ws.created', $last_day, '<');
    }

    return $num_rows = $query->countQuery()->execute()->fetchField();
  }

  /**
   * Provides average feedback for a node in a time frame.
   *
   * @param int $node_id
   *   The node id where you want to see the seen statistics.
   * @param string $date_range
   *   The range statistics which can today, week, month, year, all.
   *
   * @return string
   *   The average feedback in a time frame.
   */
  public function webformSubmissionAverageStatistics($node_id, $date_range = 'all') {
    $query = $this->database->select('webform_submission', 'ws')
      ->fields('wsd', ['value'])
      ->condition('ws.entity_type', 'node')
      ->condition('ws.entity_id', $node_id)
      ->condition('ws.webform_id', 'content_specific_webform')
      ->condition('wsd.value', NULL, 'IS NOT NULL')
      ->condition('wsd.name', 'content_relevant');
    $query->join('webform_submission_data', 'wsd', 'ws.sid = wsd.sid');

    if ($date_range === 'today') {
      $first_day = strtotime('today midnight');
      $last_day = strtotime('tomorrow');
    }
    elseif ($date_range === 'week') {
      $first_day = strtotime('monday this week');
      $last_day = strtotime('monday next week');

    }
    elseif ($date_range === 'month') {
      $first_day = strtotime('first day of this month 00:00:00');
      $last_day = strtotime('first day of next month 00:00:00');
    }
    elseif ($date_range === 'year') {
      $first_day = strtotime('first day of this year 00:00:00');
      $last_day = strtotime('first day of next year 00:00:00');
    }

    if (isset($first_day) && isset($last_day)) {
      $query->condition('ws.created', $first_day, '>=');
      $query->condition('ws.created', $last_day, '<');
    }
    $result = $query->execute()->fetchAll();
    $sum = 0;
    foreach ($result as $feedback) {
      if ($feedback->value === '1') {
        $sum += 1;
      }
    }
    if (count($result) === 0) {
      return '0%';
    }
    return ($sum / count($result)) * 100 . '%';
  }

  /**
   * Provides number of comment submissions for a node in a time frame.
   *
   * @param int $node_id
   *   The node id where you want to see the seen statistics.
   * @param string $comment_id
   *   The id of comment type.
   * @param string $date_range
   *   The range statistics which can today, week, month, year, all.
   *
   * @return int
   *   The record number in a time frame.
   */
  public function commentCountStatistics($node_id, $comment_id, $date_range = 'all') {
    $query = $this->database->select('comment_field_data', 'cfd')
      ->fields('cfd', [])
      ->condition('cfd.entity_type', 'node')
      ->condition('cfd.entity_id', $node_id)
      ->condition('cfd.comment_type', $comment_id);

    if ($date_range === 'today') {
      $first_day = strtotime('today midnight');
      $last_day = strtotime('tomorrow');
    }
    elseif ($date_range === 'week') {
      $first_day = strtotime('monday this week');
      $last_day = strtotime('monday next week');

    }
    elseif ($date_range === 'month') {
      $first_day = strtotime('first day of this month 00:00:00');
      $last_day = strtotime('first day of next month 00:00:00');
    }
    elseif ($date_range === 'year') {
      $first_day = strtotime('first day of this year 00:00:00');
      $last_day = strtotime('first day of next year 00:00:00');
    }

    if (isset($first_day) && isset($last_day)) {
      $query->condition('cfd.created', $first_day, '>=');
      $query->condition('cfd.created', $last_day, '<');
    }

    return $num_rows = $query->countQuery()->execute()->fetchField();
  }

}
