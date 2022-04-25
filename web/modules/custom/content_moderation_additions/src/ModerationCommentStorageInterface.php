<?php

namespace Drupal\content_moderation_additions;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Interface for moderation comments storage.
 */
interface ModerationCommentStorageInterface {

  /**
   * Posts a new moderation comment.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being moderated.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user posting the comment.
   * @param string $message
   *   The message entered by the user; may be appended with moderation details.
   * @param int $vid
   *   The currently loaded revision ID.
   * @param string $new_state
   *   The new state ID for the entity.
   */
  public function postComment(ContentEntityInterface $entity, AccountInterface $account, $message, $vid, $new_state = NULL);

}
