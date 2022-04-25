<?php

namespace Drupal\content_moderation_additions;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface ModerationCommentStorageInterface.
 */
interface ModerationNodeReviewerInterface {

  /**
   * Retrieves an array of user IDs that are eligible to review this entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being moderated.
   */
  public function getValidReviewers(ContentEntityInterface $entity);

  /**
   * Check to see if reviewer is a valid choice for this entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being moderated.
   * @param string $reviewer_id
   *   The reviewer to be checked to ensure they are vaild reviewer of this
   *   entity.
   */
  public function isValidReviewer(ContentEntityInterface $entity, $reviewer_id);

  /**
   * Retrieves the currently assigned reviewer.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being moderated.
   */
  public function getCurrentReviewer(ContentEntityInterface $entity);

  /**
   * Assigns the content to a reviewer.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being moderated.
   * @param string $reviewer_id
   *   The reviewer you wish to set for the entity.
   */
  public function setCurrentReviewer(ContentEntityInterface $entity, $reviewer_id);

}
