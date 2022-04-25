<?php

namespace Drupal\react_comments\Model;

class UserBase extends Base {

  protected $id;
  protected $name;
  protected $email;
  protected $thumbnail;
  protected $permissions;

  public function setId($id) {
    $this->id = $id;
    return $this;
  }

  public function getId() {
    return (int) $this->id;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->checkPlain($this->name);
  }

  public function setEmail($email) {
    $this->email = $email;
    return $this;
  }

  public function getEmail() {
    return $this->checkPlain($this->email);
  }

  public function getIpAddress() {
    // @todo dependency injection
    return \Drupal::request()->getClientIp();
  }

  public function setThumbnail($url) {
    $prefer_gravatar = \Drupal::config('react_comments.settings')->get('prefer_gravatar');

    if ($prefer_gravatar || empty($url)) {
      $this->thumbnail = $this->getGravatar($this->getEmail());
    }
    else {
      $this->thumbnail = $url;
    }

    $this->thumbnail = $url;

    return $this;
  }

  public function getThumbnail() {
    if (!$this->thumbnail) {
      $this->thumbnail = $this->getGravatar($this->getEmail());
    }
    return $this->thumbnail;
  }

  public function getPermissions() {
    return $this->permissions;
  }

  public function setPermissions($permissions) {
    $this->permissions = $permissions;
    return $this;
  }

  public function hasPermission($permission) {
    return in_array($permission, $this->permissions);
  }

  public function model() {
    return [
      'id'            => $this->getId(),
      'name'          => $this->getName(),
      'thumbnail'     => $this->getThumbnail(),
      'email'         => $this->getEmail(),
      'isAnon'        => ($this->getId() === 0),
      'permissions'   => $this->getPermissions()
    ];
  }

  /**
   * Get either a Gravatar URL or complete image tag for a specified email address.
   *
   * @param string $email The email address
   * @param string $s Size in pixels, defaults to 80px [ 1 - 2048 ]
   * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
   * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
   * @param boole $img True to return a complete IMG tag False for just the URL
   * @param array $atts Optional, additional key/value attributes to include in the IMG tag
   * @return String containing either just a URL or a complete image tag
   * @source https://gravatar.com/site/implement/images/php/
   */
  protected function getGravatar( $email, $s = 80, $d = 'mm', $r = 'g', $img = FALSE, $atts = [] ) {
    $url = 'https://www.gravatar.com/avatar/';
    $url .= md5( strtolower( trim( $email ) ) );
    $url .= "?s=$s&amp;d=$d&amp;r=$r";
    if ( $img ) {
      $url = '<img src="' . $url . '"';
      foreach ( $atts as $key => $val )
        $url .= ' ' . $key . '="' . $val . '"';
      $url .= ' />';
    }
    return $url;
  }

}
