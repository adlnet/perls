<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\user\UserInterface;
use Drupal\comment\Entity\Comment;
use Drupal\Core\Entity\EntityInterface;
use Drupal\xapi\XapiStatement;
use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define test attempted state.
 *
 * @XapiState(
 *  id = "xapi_user_commented",
 *  label = @Translation("User left a comment"),
 *  add_verb = @XapiVerb("commented"),
 *  notifyOnXapi = TRUE,
 *  flag = ""
 * )
 */
class UserCommented extends XapiStateBase {

  /**
   * A drupal comment.
   *
   * @var \Drupal\comment\Entity\Comment
   */
  protected $commentEntity;

  /**
   * {@inheritdoc}
   */
  public function getReadyStatement(?EntityInterface $entity, int $timestamp = NULL, UserInterface $user = NULL): ?XapiStatement {
    /** @var \Drupal\comment\Entity\Comment $entity */
    $this->setComment($entity);
    return parent::getReadyStatement($entity->getCommentedEntity(), $timestamp, $user);
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareStatement(int $timestamp = NULL, UserInterface $user = NULL) {
    /** @var \Drupal\comment\Entity\Comment $comment */
    parent::prepareStatement($timestamp, $user);
    $this->setStatementResult($this->getComment());
  }

  /**
   * Set the result in the statement.
   *
   * @param \Drupal\comment\Entity\Comment $entity
   *   A drupal comment entity.
   */
  protected function setStatementResult(Comment $entity) {
    $this->statement
      ->setResultResponse($entity->get('comment_body')->value)
      ->addResultExtensions([
        'http://activitystrea.ms/schema/1.0/comment' => $entity->toUrl('canonical', ['absolute' => TRUE])->toString(),
      ]);
  }

  /**
   * Gives back of comment entity.
   *
   * @return \Drupal\comment\Entity\Comment
   *   A drupal comment entity.
   */
  public function getComment() {
    return $this->commentEntity;
  }

  /**
   * Set the comment.
   *
   * @param \Drupal\comment\Entity\Comment $comment
   *   A comment which belongs to statement.
   *
   * @return $this
   *   The instance itself.
   */
  public function setComment(Comment $comment) {
    $this->commentEntity = $comment;
    return $this;
  }

}
