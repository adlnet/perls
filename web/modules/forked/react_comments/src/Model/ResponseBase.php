<?php

namespace Drupal\react_comments\Model;

use Drupal\Core\StringTranslation\StringTranslationTrait;

class ResponseBase {

  use StringTranslationTrait;

  protected $data;
  protected $code;


  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function getData() {
    return $this->data;
  }

  public function setCode($code) {
    $this->code = $code;
    return $this;
  }

  public function getCode() {
    return $this->code;
  }

  public function getMessage() {
    $messages = [
      'success'                => $this->t('Success'),
      'invalid_nid'            => $this->t('Invalid Entity ID supplied'),
      'nid_not_found'          => $this->t('Entity ID not found'),
      'invalid_cid'            => $this->t('Invalid Comment ID supplied'),
      'cid_not_found'          => $this->t('Comment ID not found'),
      'invalid_uid'            => $this->t('Invalid User ID supplied'),
      'uid_not_found'          => $this->t('User ID not found'),
      'no_comments_found'      => $this->t('No comments found for supplied entity'),
      'comment_deleted'        => $this->t('Comment does not exist'),
      'already_deleted'        => $this->t('Comment was already deleted'),
      'already_flagged'        => $this->t('Comment was already flagged'),
      'not_authorized'         => $this->t('You are not authorized. Please login'),
      'queued_for_moderation'  => $this->t('Your comment has been queued for moderation'),
      'comments_closed'        => $this->t('Comments are closed for this post'),
      'comments_hidden'        => $this->t('Comments are disabled for this post'),
      'anon_contact_required'  => $this->t('Contact info for anonymous users is required'),
      'anon_contact_forbidden' => $this->t('Contact info for anonymous users is forbidden')
    ];

    return !empty($messages[$this->getCode()])
      ? $messages[$this->getCode()]
      : $this->t('Unknown error occured');
  }

  public function model() {
    return [
      'code'    => $this->getCode(),
      'message' => $this->getMessage(),
      'data'    => $this->getData(),
    ];
  }

}
