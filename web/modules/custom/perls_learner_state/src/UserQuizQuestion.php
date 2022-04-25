<?php

namespace Drupal\perls_learner_state;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Help to calculate the number of questions in a test for the current user..
 */
class UserQuizQuestion {

  /**
   * The account of the current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * A helper class for history module.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user.
   */
  public function __construct(AccountInterface $currentUser) {
    $this->currentUser = $currentUser;
  }

  /**
   * Helper method to get the count of published quiz in a test.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Test entity.
   *
   * @return int
   *   Return total count.
   */
  public function getQuiz(EntityInterface $entity) {
    $quizzes = [];
    if (!empty($entity->field_quiz)) {
      foreach ($entity->field_quiz->referencedEntities() as $quiz) {
        if ($quiz->access('view', $this->currentUser)) {
          $quizzes[] = $quiz;
        }
      }
    }

    return count($quizzes);
  }

}
