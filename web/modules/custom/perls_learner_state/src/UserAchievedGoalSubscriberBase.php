<?php

namespace Drupal\perls_learner_state;

/**
 * Base class to all subscriber which listening because user's goal.
 */
trait UserAchievedGoalSubscriberBase {

  /**
   * Request user goal check.
   *
   * @param array $data
   *   An array which contain the necessary data to check user's goals.
   */
  protected function addItemQueue(array $data) {
    $queue = \Drupal::service('queue')->get('perls_goal_log_check_goal');
    $queue->createItem($data);
  }

}
