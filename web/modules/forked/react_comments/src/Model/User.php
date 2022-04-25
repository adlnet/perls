<?php

namespace Drupal\react_comments\Model;

use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\user\Entity\User as DrupalUser;

class User extends UserBase {

  public function __construct(AccountInterface $user = NULL) {
    if ($user) {
      $this->load($user);
    }
  }

  public function load(AccountInterface $user = NULL) {
    $user = $user ?: DrupalUser::load($this->getId());

    if ($user) {
      // load the full user entity so we can access the user picture field
      $user = DrupalUser::load($user->id());

      $user_picture = null;

      if ($user->isAnonymous()) {
        $user_avatar_image_style = \Drupal::config('react_comments.settings')->get('user_avatar_image_style') ?: 'thumbnail';
        $default_anon_avatar_fid = \Drupal::config('react_comments.settings')->get('anon_default_avatar_fid') ?: null;

        if ($default_anon_avatar_fid) {
          $default_anon_avatar_file = File::load($default_anon_avatar_fid[0]);
          $user_picture = ImageStyle::load($user_avatar_image_style)->buildUrl($default_anon_avatar_file->getFileUri());
        }
      }
      else if (isset($user->user_picture) && !$user->user_picture->isEmpty()) {
        $user_avatar_image_style = \Drupal::config('react_comments.settings')->get('user_avatar_image_style') ?: 'thumbnail';
        $uri = $user->user_picture->entity->uri->value;
        $user_picture = ImageStyle::load($user_avatar_image_style)->buildUrl($uri);
      }

      if (is_null($user_picture)) {
        $user_picture = $this->getDefault_user_image();
      }

      $this
        ->setId($user->id())
        ->setName($user->id() ? $user->getDisplayName() : null)
        ->setEmail($user->getEmail())
        ->setThumbnail($user_picture)
        ->setPermissions($this->loadPermissions($user));
    }
  }

  private function loadPermissions(AccountInterface $user) {
    $permissions = [
      'administer comments',
      'administer comment types',
      'access comments',
      'post comments',
      'skip comment approval',
      'edit own comments'
    ];
    foreach ($permissions as $key => $permission) {
      if (!$user->hasPermission($permission)) {
        unset($permissions[$key]);
      }
    }
    return $permissions;
  }

  // Get User default image
  private function getDefault_user_image() {
    $field = \Drupal\field\Entity\FieldConfig::loadByName('user', 'user', 'user_picture');
    $default_image = $field->getSetting('default_image');

    if ($default_image) {
      $user_avatar_image_style = \Drupal::config('react_comments.settings')->get('user_avatar_image_style') ?: 'thumbnail';
      $file = \Drupal::service('entity.repository')->loadEntityByUuid('file', $default_image['uuid']);
      return ImageStyle::load($user_avatar_image_style)->buildUrl($file->getFileUri());
    }
  }
}
