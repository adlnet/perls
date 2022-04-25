<?php

namespace Drupal\react_comments\Model;

use \Drupal\Component\Render\HtmlEscapedText;

class Base {

  protected $drupal_user;
  protected $roles;
  protected $cache;

  public function __construct() {
    $this->drupal_user = \Drupal::currentUser();
    $this->cache = \Drupal::cache();
  }

  public function isAdmin() {
    // Only admins should be able to edit comments.
    return $this->drupal_user->hasPermission('administer comments');
  }

  public function checkPlain($text) {
    return (new HtmlEscapedText($text))->__toString();
  }

}
