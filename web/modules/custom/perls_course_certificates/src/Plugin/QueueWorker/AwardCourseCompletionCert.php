<?php

namespace Drupal\perls_course_certificates\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Processes tasks for example module.
 *
 * @QueueWorker(
 *   id = "award_course_completion_cert",
 *   title = @Translation("Award Course Completion Certificates"),
 *   cron = {"time" = 20}
 * )
 */
class AwardCourseCompletionCert extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    $achievement_id = $item['achievement_id'];
    $entity_id = $item['entity_id'];
    $badge_service = \Drupal::service('badges.badge_service');
    $achievement = $badge_service->getAchievementById('course_completion_' . $entity_id);
    if (!$achievement) {
      return;
    }
    // Need to award this to all users who have completed this content.
    $flagging = \Drupal::entityTypeManager()
      ->getStorage('flagging')
      ->loadByProperties(['flag_id' => 'completed', 'entity_id' => $entity_id]);
    foreach ($flagging as $flag) {
      $badge_service->awardBadge($flag->getOwner(), $achievement_id, $flag->created->value);
    }

  }

}
