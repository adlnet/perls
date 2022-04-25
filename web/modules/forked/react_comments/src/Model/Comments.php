<?php

namespace Drupal\react_comments\Model;

class Comments extends CommentsBase {

  /**
   * Load all comments.
   */
  public function load($show_unpublished = FALSE) {
    $query = \Drupal::database()->select('comment_field_data', 'c')
      ->fields('c', ['cid'])
      ->condition('c.entity_id', $this->getEntityId())
      ->condition('c.pid', NULL, 'IS NULL')
      ->orderBy('c.created', 'DESC')
      ->addTag('react_comments_load_comments');

    if (!$show_unpublished) {
      // if the user doesn't have the 'administer comments' permission, only load published comments
      $query->condition('c.status', RC_COMMENT_PUBLISHED);
    }

    $result = $query->execute();

    $thread = [];
    foreach ($result as $record) {
      if ($comment = (new Comment())->setId($record->cid)->load($show_unpublished)) {
        $thread[] = $comment->model();
      }
    }
    if (empty($thread)) return NULL;
    $this->setComments($thread);
    return $this;
  }

}
