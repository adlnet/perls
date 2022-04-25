<?php

namespace Drupal\perls_learner_state\EventSubscriber;

use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\perls_learner_state\LearnerInfo;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles tracking completion of content.
 *
 * Currently handles setting the course completion flag when
 * the course or user flags change.
 */
class CompletionSubscriber implements EventSubscriberInterface {

  /**
   * Service to retrieve learner-specific information.
   *
   * @var \Drupal\perls_learner_state\LearnerInfo
   */
  protected $learnerInfo;

  /**
   * Constructs a new CompletionSubscriber object.
   *
   * @param \Drupal\perls_learner_state\LearnerInfo $learnerInfo
   *   Service for looking up learner-specific information.
   */
  public function __construct(LearnerInfo $learnerInfo) {
    $this->learnerInfo = $learnerInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['flag.entity_flagged'] = ['handleFlaggingEvent'];

    return $events;
  }

  /**
   * Checks completion status for all enrolled users.
   *
   * @param \Drupal\node\NodeInterface $course
   *   The course.
   */
  public function checkCompletionForEnrolledUsers(NodeInterface $course) {
    $enrolledAccounts = $this->learnerInfo->getEnrolledUsers($course);
    foreach ($enrolledAccounts as $account) {
      $this->checkCourseCompletion($course, $account);
    }
  }

  /**
   * Checks to see if the course completion flag should be set.
   *
   * When a flagging changes, this checks to see if it was a completion flag
   * and, if so, finds any related courses and checks to see if the content
   * has been completed.
   *
   * @param \Drupal\flag\Event\FlaggingEvent $event
   *   An event which (may) represent a newly completed piece of content.
   */
  public function handleFlaggingEvent(FlaggingEvent $event) {
    $flagging = $event->getFlagging();
    if ($flagging->getFlagId() !== LearnerInfo::COMPLETED_FLAG
      || $flagging->getFlaggableType() === LearnerInfo::COURSE_ENTITY) {
      return;
    }

    $flag = $flagging->getFlag();
    $account = $flagging->getOwner();

    // Find the courses (if any) that this content belongs to.
    $nids = \Drupal::entityQuery('node')
      ->condition('type', LearnerInfo::COURSE_ENTITY)
      ->condition('field_learning_content.target_id', $flagging->getFlaggableId())
      ->execute();

    $courses = Node::loadMultiple($nids);

    foreach ($courses as $course) {
      $this->checkCourseCompletion($course, $account, $flagging->created->value);
    }
  }

  /**
   * Checks course completion status for a specific user.
   *
   * Updates completion status for the user if they have
   * completed all available course content.
   *
   * @param \Drupal\node\Entity\NodeInterface $course
   *   The course.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   * @param int $timestamp
   *   Allows for back-dating of the completion.
   */
  private function checkCourseCompletion(NodeInterface $course, AccountInterface $account, int $timestamp = NULL) {
    if ($course->getType() !== LearnerInfo::COURSE_ENTITY) {
      throw new \InvalidArgumentException($course->getType() . ' is not a course');
    }

    // If the course is _not_ complete, but all the content is complete,
    // then it's time to mark the course as complete.
    if (!$this->learnerInfo->isCourseComplete($course, $account)
      && $this->learnerInfo->isCourseContentComplete($course, $account)) {
      $courseFlagging = $this->learnerInfo->markCourseComplete($course, $account);

      if ($timestamp) {
        $courseFlagging->created = $timestamp;
        $courseFlagging->save();
      }
    }
  }

}
