<?php

namespace Drupal\perls_goals_log\Plugin\QueueWorker;

use Drupal\perls_goals_log\GoalCheckerWorkerBase;

/**
 * Use cron to send achieved goal event.
 *
 * @QueueWorker(
 *   id = "perls_goal_log_check_goal",
 *   title = @Translation("Check user goals"),
 *   cron = {"time" = 30}
 * )
 */
class AchievedGoalCheckerWorker extends GoalCheckerWorkerBase {

  /**
   * Queue item worker.
   */
  public function processItem($data) {
    $goal_type = $data['goal_type'];
    if (empty($data['user']) || empty($data['goal_type'])) {
      return;
    }
    $goals = $this->goalHelper->getGoalsByType($goal_type);

    if (empty($goals)) {
      return;
    }

    parent::processQueueItems($data['user'], $goals);
  }

}
