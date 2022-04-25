<?php

namespace Drupal\badges\Entity;

use Drupal\achievements\Entity\AchievementEntity as AchievementEntityOriginal;
use Drupal\Core\Session\AccountInterface;
use Drupal\badges\ExtendedAchievementInterface;
use Drupal\badges\ImageGenerationAchievementInterface;
use Drupal\user\Entity\User;

/**
 * Defines and override for AchievementEntity.
 */
class AchievementEntity extends AchievementEntityOriginal implements ExtendedAchievementInterface {

  /**
   * The default type to return if none is set.
   */
  const DEFAULT_TYPE = 'badge';

  /**
   * {@inheritdoc}
   */
  public function getStorage() {
    // We get storage name from the plugin.
    if ($plugin = $this->getAchievementPlugin()) {
      return $plugin->getStorage($this);
    }
    else {
      // If no plugin exists we go back to default behavior.
      parent::getStorage();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultImagePath($filename) {
    return 'public://badges/' . $filename;
  }

  /**
   * {@inheritdoc}
   */
  public function getImagePath($type = 'locked', $allow_default = TRUE) {
    switch ($type) {
      case 'locked':
        return $this->getLockedPath($allow_default);

      case 'unlocked':
      case 'sharable':
        return $this->getUnlockedPath($allow_default);

      default:
        return $this->getDefaultImagePath('defaultLocked.png');
    }
  }

  /**
   * Return the path to the unlocked image.
   */
  public function getUnlockedPath($allow_default = TRUE) {
    if ($allow_default && empty($this->unlocked_image_path)) {
      // By default we return a fixed default image path.
      $path = $this->getDefaultImagePath('defaultUnlocked.png');
      // This value can be overridden by config.
      if ($default_config = \Drupal::config('achievements.settings')->get('default_unlocked_image')) {
        $path = $default_config;
      }
      // Individual plugins can also override the default image.
      $plugin = $this->getAchievementPlugin();
      if ($plugin && $plugin_default = $plugin->getDefaultImage('unlocked')) {
        $path = $plugin_default;
      }
      return $path;
    }
    else {
      return $this->unlocked_image_path;
    }
  }

  /**
   * Return the path to the unlocked image.
   */
  public function getLockedPath($allow_default = TRUE) {
    if ($allow_default &&empty($this->locked_image_path)) {
      // By default we return a fixed default image path.
      $path = $this->getDefaultImagePath('defaultLocked.png');
      // This value can be overridden by config.
      if ($default_config = \Drupal::config('achievements.settings')->get('default_locked_image')) {
        $path = $default_config;
      }
      // Individual plugins can also override the default image.
      $plugin = $this->getAchievementPlugin();
      if ($plugin && $plugin_default = $plugin->getDefaultImage('locked')) {
        $path = $plugin_default;
      }
      return $path;
    }
    else {
      return $this->locked_image_path;
    }
  }

  /**
   * Return the path to the locked image.
   */

  /**
   * {@inheritdoc}
   */
  public function getImageUrl(AccountInterface $user = NULL, $image_type = 'locked') {
    if (!$user) {
      $user = \Drupal::currentUser();
    }
    $user = User::load($user->id());

    $plugin = $this->getAchievementPlugin();

    if ($plugin && $plugin instanceof ImageGenerationAchievementInterface) {
      return $plugin->generateImage($this, $user, $image_type);
    }
    else {
      return file_create_url($this->getImagePath($image_type));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    $type = $this->getThirdPartySetting('badges', 'bundle');
    return ($type) ?: self::DEFAULT_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo(AccountInterface $user) {
    $unlock = $this->getUnlocked($user);
    $achievement =
      [
        'id' => $this->id(),
        'uuid' => $this->uuid(),
        'label' => $this->label(),
        'description' => $this->getDescription(),
        'type' => $this->getType(),
        'plugin_type' => $this->getThirdPartySetting('badges', 'badge_type'),
        'secret' => $this->isSecret(),
        'invisible' => $this->isInvisible(),
        'locked_image_url' => $this->getImageUrl($user, 'locked'),
        'unlocked_image_url' => $this->getImageUrl($user, 'unlocked'),
        'sharable_image_url' => $this->getImageUrl($user, 'sharable'),
        'unlocked' => ($unlock) ? 1 : 0,
        'unlocked_timestamp' => (isset($unlock['timestamp'])) ? $unlock['timestamp'] : NULL,
        'status_description' => ($unlock) ? t('Awarded') : t('Achievement Locked'),
      ];
    return $achievement;

  }

  /**
   * Check to see if this achievement has been unlocked.
   */
  protected function getUnlocked(AccountInterface $user) {
    return achievements_unlocked_already($this->id(), $user->id());
  }

  /**
   * Get the acheivement plugin for this object.
   */
  protected function getAchievementPlugin() {
    // Config Entities cannot use dependency injection so I have to include
    // the service directly here.
    $badge_service = \Drupal::service('badges.badge_service');
    return $badge_service->getBadgePlugin($this->getThirdPartySetting('badges', 'plugin_id'));
  }

}
