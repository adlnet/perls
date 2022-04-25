<?php

namespace Drupal\perls_group_management;

use Drupal\Core\Database\Connection;

/**
 * Helper class for group statistics.
 *
 * @package Drupal\perls_group_management
 */
class GroupStatistics {

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * GroupStatistics constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Gives back the number of members in a group.
   *
   * @param int $group_id
   *   The group id.
   *
   * @return int
   *   Number of members.
   */
  public function numberOfMembers($group_id) {
    $query = $this->database->select('group_content_field_data', 'gcfd')
      ->fields('gcfd', [])
      ->condition('gcfd.type', 'audience-group_membership')
      ->condition('gcfd.gid', $group_id);

    return $num_rows = $query->countQuery()->execute()->fetchField();
  }

  /**
   * Provides content seen statistics for a group.
   *
   * @param int $group_id
   *   The group id where you want to see the seen statistics.
   * @param string $date_range
   *   The range statistics which can today, week, month, all.
   *
   * @return int
   *   The record number in a time frame.
   */
  public function seenContentStatics($group_id, $date_range = 'all') {
    $query = $this->database->select('group_content_field_data', 'gcfd')
      ->fields('gcfd', [])
      ->condition('gcfd.type', 'audience-group_membership')
      ->condition('gcfd.gid', $group_id);
    $query->join('history', 'h', 'gcfd.entity_id = h.uid');

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

    if (isset($first_day) && isset($last_day)) {
      $query->condition('h.timestamp', $first_day, '>=');
      $query->condition('h.timestamp', $last_day, '<');
    }

    return $num_rows = $query->countQuery()->execute()->fetchField();
  }

  /**
   * Provides number of flagged content statistics for a group in a time frame.
   *
   * @param int $group_id
   *   The group id where you want to see the seen statistics.
   * @param string $flag_name
   *   The id of flag.
   * @param string $date_range
   *   The range statistics which can today, week, month, all.
   *
   * @return int
   *   The record number in a time frame.
   */
  public function flaggedContentStatics($group_id, $flag_name, $date_range = 'all') {
    $query = $this->database->select('group_content_field_data', 'gcfd')
      ->fields('gcfd', [])
      ->condition('gcfd.type', 'audience-group_membership')
      ->condition('gcfd.gid', $group_id);
    $query->join('flagging', 'f', 'gcfd.entity_id = f.uid');
    $query->condition('f.flag_id', $flag_name);

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

    if (isset($first_day) && isset($last_day)) {
      $query->condition('f.created', $first_day, '>=');
      $query->condition('f.created', $last_day, '<');
    }

    return $num_rows = $query->countQuery()->execute()->fetchField();
  }

}
