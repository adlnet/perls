<?php

namespace Drupal\perls_learner_state;

use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\flag\FlagServiceInterface;

/**
 * Provides convenience methods for looking up learner-specific information.
 *
 * Particularly useful for looking up a user's course progress.
 */
class LearnerInfo {

  const COMPLETED_FLAG = 'completed';
  const COURSE_ENTITY = 'course';

  /**
   * Drupal\Core\Database\Driver\mysql\Connection definition.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Drupal entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LearnerInfo object.
   */
  public function __construct(
    Connection $database,
    AccountProxyInterface $current_user,
    FlagServiceInterface $flag,
    EntityTypeManagerInterface $entity_type_manager) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->flagService = $flag;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Retrieves all enrolled users in a course.
   *
   * @param \Drupal\node\NodeInterface $course
   *   The course.
   *
   * @return \Drupal\Core\Session\AccountInterface[]
   *   The enrolled users.
   */
  public function getEnrolledUsers(NodeInterface $course) {
    $flag = $this->flagService->getFlagById('started_course');
    return $this->flagService->getFlaggingUsers($course, $flag);
  }

  /**
   * Determines whether a learner is enrolled in a course.
   *
   * Currently, "enrolled" merely means the user has completed
   * anything within the course.
   *
   * @param \Drupal\node\NodeInterface $course
   *   The course.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The learner; defaults to the current user.
   *
   * @return bool
   *   TRUE if the learner is enrolled in the course; FALSE otherwise.
   */
  public function isEnrolled(NodeInterface $course, AccountInterface $account = NULL) {
    return $this->getCourseProgress($course, $account) > 0;
  }

  /**
   * Checks whether all the _content_ in a course is complete.
   *
   * @param \Drupal\node\NodeInterface $course
   *   The course.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The learner; defaults to the current user.
   *
   * @return bool
   *   TRUE if all of the content in the course has been completed.
   */
  public function isCourseContentComplete(NodeInterface $course, AccountInterface $account = NULL) {
    return $this->getCourseProgress($course, $account) >= $this->getCourseLength($course, $account);
  }

  /**
   * Checks whether the course has been flagged as complete.
   *
   * A course may be flagged as complete even if it has incomplete content.
   *
   * @param \Drupal\node\NodeInterface $course
   *   The course.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The learner; defaults to the current user.
   *
   * @return bool
   *   TRUE if the course is marked as complete.
   */
  public function isCourseComplete(NodeInterface $course, AccountInterface $account = NULL) {
    $flag = $this->flagService->getFlagById(LearnerInfo::COMPLETED_FLAG);
    return $this->flagService->getFlagging($flag, $course, $account) !== NULL;
  }

  /**
   * Mark a course as complete.
   *
   * @param \Drupal\node\NodeInterface $course
   *   The course.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The learner; defaults to the current user.
   *
   * @return FlaggingInterface
   *   The flagging representing the completion.
   */
  public function markCourseComplete(NodeInterface $course, AccountInterface $account = NULL) {
    $flag = $this->flagService->getFlagById(LearnerInfo::COMPLETED_FLAG);
    $flagging = $this->flagService->getFlagging($flag, $course, $account);

    if ($flagging === NULL) {
      $flagging = $this->flagService->flag($flag, $course, $account);
    }

    return $flagging;
  }

  /**
   * Gets the number of learning objects in the course.
   *
   * This number is filtered by objects that the user can access.
   *
   * @param \Drupal\node\NodeInterface $course
   *   The course.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The learner; defaults to the current user.
   *
   * @return int
   *   The number of accessible learning objects.
   */
  public function getCourseLength(NodeInterface $course, AccountInterface $account = NULL) {
    if ($course->getType() !== LearnerInfo::COURSE_ENTITY || !$course->hasField('field_learning_content')) {
      throw new \InvalidArgumentException('Unable to determine course length on a ' . $course->getType());
    }

    $accessibleContent = array_filter($course->get('field_learning_content')->referencedEntities(), function ($content) use ($account) {
      return $content->access('view', $account);
    });

    return count($accessibleContent);
  }

  /**
   * Retrieves the course progress for a learner.
   *
   * "Progress" refers to the number of completed learning objects
   * within that course.
   *
   * @param \Drupal\node\NodeInterface $course
   *   The course.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The learner; defaults to the current user.
   *
   * @return int
   *   The number of completed learning objects in the course.
   */
  public function getCourseProgress(NodeInterface $course, AccountInterface $account = NULL) {
    if ($course->getType() !== LearnerInfo::COURSE_ENTITY || !$course->hasField('field_learning_content')) {
      throw new \InvalidArgumentException('Unable to determine course progress on a ' . $course->getType());
    }

    if (!$account) {
      $account = $this->currentUser;
    }

    $allContent = array_column($course->get('field_learning_content')->getValue(), 'target_id');
    if (empty($allContent)) {
      return 0;
    }

    $accessibleContent = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('nid', $allContent, 'IN')
      ->execute();

    if (empty($accessibleContent)) {
      return 0;
    }

    $query = $this->database
      ->select('flagging', 'f')
      ->condition('flag_id', LearnerInfo::COMPLETED_FLAG)
      ->condition('entity_type', 'node')
      ->condition('entity_id', $accessibleContent, 'IN')
      ->condition('uid', $account->id());

    $query->addExpression('COUNT(*)');
    return $query->execute()->fetchField();
  }

}
