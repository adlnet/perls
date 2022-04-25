<?php

namespace Drupal\perls_goals_log;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;

/**
 * This class help to check the goal log.
 *
 * The logs are paragraphs. Which contains the next fields:
 *   - field_goal_log_goal_name: The api name of goal field.
 *   - field_goal_log_timeframe: A time frame of the goal. This time frame
 *   indicate that how offten we need to check this goal. (week or month)
 *   - field_goal_log_time_indicator: This a number which depends on the time
 *   frame. If the time frame is a week then actual week number otherwise the
 *   actual month in number.
 */
class PerlsLogManager {

  /**
   * Paragraph storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $paragraphsStorage;

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * PerlsLogManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Drupal entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Drupal config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config) {
    $this->paragraphsStorage = $entity_type_manager->getStorage('paragraph');
    $this->config = $config;
  }

  /**
   * Load the achieved goal logs.
   *
   * @param int $uid
   *   A drupal user id.
   * @param array $log_names
   *   List of goal name.(api field name)
   * @param string $log_timeframe
   *   The time frame of the goal. (week or month)
   * @param int $log_time
   *   The week number or month number.
   *
   * @return array|null
   *   List of logs entries.
   */
  public function getLog($uid, array $log_names, $log_timeframe = NULL, $log_time = NULL) {
    $query = $this->paragraphsStorage->getQuery();
    $query->condition('type', 'goal_log');
    $query->condition('parent_type', 'user');
    $query->condition('parent_id', $uid);
    $query->condition('field_goal_log_goal_name', $log_names, 'IN');
    if ($log_timeframe) {
      $query->condition('field_goal_log_timeframe', $log_timeframe);
    }
    if ($log_time) {
      $query->condition('field_goal_log_time_indicator', $log_time);
    }
    $logs = $query->execute();
    if (!empty($logs)) {
      return Paragraph::loadMultiple($logs);
    }
    return [];
  }

  /**
   * Creates a goal achieved log entry.
   *
   * @param array $log_values
   *   The log data, expected value.
   *     'field_goal_log_goal_name': name of the goal(api name)
   *     'field_goal_log_timeframe': 'month or week'
   *     'field_goal_log_time_indicator': 'week number or month number'.
   * @param int $uid
   *   The user id who has log entry.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setLog(array $log_values, $uid) {
    $user = User::load($uid);
    $paragraph = Paragraph::create([
      'type' => 'goal_log',
      'parent_field_name' => 'field_goal_log',
    ] + $log_values);
    $paragraph->save();

    $current_values = $user->get('field_goal_log')->getValue();
    $current_values[] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
    $user->set('field_goal_log', $current_values);
    $user->save();
  }

  /**
   * Filter for field name which specific string.
   *
   * @param string $type
   *   String that you are looking in field name.
   *
   * @return array
   *   A matched field list.
   */
  public function filterGoalFields(string $type) {
    $fields = [];
    $field_list = $this->config->get('perls_goals.settings')->get('fields.field_values');
    foreach ($field_list as $field) {
      if (strpos($field['api_field'], $type) !== FALSE) {
        $fields[$field['api_field']] = $field;
      }
    }

    return $fields;
  }

}
