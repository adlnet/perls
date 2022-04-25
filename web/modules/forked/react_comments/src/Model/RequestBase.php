<?php

namespace Drupal\react_comments\Model;

class RequestBase {

  protected $request;
  protected $contents = [];

  public function __construct() {
    $this->request = \Drupal::request();
  }

  public function parseContentJson() {
    $this->contents = json_decode($this->request->getContent(), TRUE);
    return $this;
  }

  public function getJsonVal($parameter) {
    return !empty($this->contents[$parameter]) ? $this->contents[$parameter] : NULL;
  }

}
