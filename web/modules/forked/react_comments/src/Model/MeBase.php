<?php

namespace Drupal\react_comments\Model;

class MeBase extends Base {

  protected $current_user;

  public function setCurrentUser(User $current_user) {
    $this->current_user = $current_user;
    return $this;
  }

  public function getCurrentUser() {
    return $this->current_user;
  }

  public function model() {
    return [
      'current_user' => $this->getCurrentUser()->model(),
    ];
  }

}
