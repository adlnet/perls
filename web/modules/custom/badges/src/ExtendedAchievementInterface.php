<?php

namespace Drupal\badges;

use Drupal\achievements\Entity\AchievementEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Implements the Extended Achievements Interface.
 */
interface ExtendedAchievementInterface extends AchievementEntityInterface {

  /**
   * Get the image path for a given user.
   */
  public function getImageUrl(AccountInterface $user = NULL, $image_type = 'locked');

  /**
   * Get the type of this achievement.
   */
  public function getType();

  /**
   * Get this achievement as an array.
   */
  public function getInfo(AccountInterface $user);

}
