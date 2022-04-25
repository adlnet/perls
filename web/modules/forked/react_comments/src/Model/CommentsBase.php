<?php

namespace Drupal\react_comments\Model;

use Drupal\react_comments\CommentFieldSettings;

class CommentsBase extends Base {

  protected $entity_id;
  protected $comments;

  public function setEntityId($id) {
    $this->entity_id = $id;
    return $this;
  }

  public function getEntityId() {
    return (int) $this->entity_id;
  }

  public function setComments($comments) {
    $this->comments = $comments;
    return $this;
  }

  public function getComments() {
    return $this->comments;
  }

  public function model() {
    return [
      'comments' => $this->getComments(),
      'settings' => CommentFieldSettings::getCommentFieldSettings($this->getEntityId())
    ];
  }

}
