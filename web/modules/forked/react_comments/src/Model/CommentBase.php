<?php

namespace Drupal\react_comments\Model;

use Drupal\Component\Utility\Unicode;

class CommentBase extends Base {

  protected $id;
  protected $reply_id;
  protected $entity_id;
  protected $user;
  protected $subject;
  protected $comment;
  protected $vote_stats;
  protected $replies;
  protected $ip_address;
  protected $status;
  protected $created_at;
  protected $changed_at;
  protected $published;
  protected $name;
  protected $email;
  protected $field_name;
  protected $comment_type;

  public function setId($id) {
    $this->id = $id;
    return $this;
  }

  public function getId() {
    return (int) $this->id;
  }

  public function setReplyId($id) {
    $this->reply_id = (int) $id;
    return $this;
  }

  public function getReplyId() {
    return (int) $this->reply_id;
  }

  public function setEntityId($id) {
    $this->entity_id = $id;
    return $this;
  }

  public function getEntityId() {
    return (int) $this->entity_id;
  }

  public function setUser(User $user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->user;
  }

  public function getName() {
    return $this->name;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getEmail() {
    return $this->email;
  }

  public function setEmail($email) {
    $this->email = $email;
    return $this;
  }

  public function setSubject($subject) {
    $this->subject = $subject;
    return $this;
  }

  public function getSubject() {
    return empty($this->subject) ? Unicode::truncate($this->getComment(), 60) : $this->subject;
  }

  public function setComment($comment) {
    $this->comment = $comment;
    return $this;
  }

  public function getComment() {
    $allowed_tags = \Drupal::config('react_comments.settings')->get('allowed_tags');
    return !empty($this->comment) ? strip_tags($this->comment, $allowed_tags) : '';
  }

  public function setReplies(array $replies) {
    $this->replies = $replies;
    return $this;
  }

  public function getReplies() {
    return !empty($this->replies) ? $this->replies : NULL;
  }

  public function setIpAddress($ip = NULL) {
    $this->ip_address = $ip;
    return $this;
  }

  public function getIpAddress() {
    if (empty($this->ip_address)) {
      return \Drupal::request()->getClientIp();
    }
    return $this->ip_address;
  }

  public function setStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function getStatus() {
    return $this->status;
  }

  public function isVisible() {
    return $this->status == RC_COMMENT_PUBLISHED;
  }

  public function isPublished() {
    return $this->published;
  }

  public function setPublishedStatus($status) {
    $this->published = $status;
    return $this;
  }

  public function setCreatedAt($timestamp = NULL) {
    $this->created_at = $timestamp;
    return $this;
  }

  public function getCreatedAt() {
    if (empty($this->created_at)) {
      return time();
    }
    return $this->created_at;
  }

  public function setChangedAt($timestamp = NULL) {
    $this->changed_at = $timestamp;
    return $this;
  }

  public function getChangedAt() {
    if (empty($this->changed_at)) {
      return time();
    }
    return $this->changed_at;
  }

  public function model() {
    return [
      'id'         => $this->getId(),
      'nid'        => $this->getEntityId(),
      'subject'    => $this->isVisible() ? $this->getSubject() : NULL,
      'comment'    => $this->isVisible() ? $this->getComment() : '',
      'status'     => $this->getStatus(),
      'published'  => $this->isPublished(),
      'user'       => $this->getUser()->model(),
      'created_at' => $this->getCreatedAt(),
      'replies'    => $this->getReplies(),
      'name'       => $this->isVisible() ? $this->getName() : NULL,
      'email'      => $this->isVisible() ? $this->getEmail() : NULL
    ];
  }

  public function getFieldName() {
    return $this->field_name;
  }

  public function setFieldName($field_name) {
    $this->field_name = $field_name;
    return $this;
  }

  public function getCommentType() {
    return $this->comment_type;
  }

  public function setCommentType($comment_type) {
    $this->comment_type = $comment_type;
    return $this;
  }

}
