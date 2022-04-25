<?php

namespace Drupal\badges;

use Drupal\badges\Entity\AchievementEntity;
use Drupal\user\UserInterface;

/**
 * An interface for achievements that generate images.
 */
interface ImageGenerationAchievementInterface {

  /**
   * Generates the image of type for this achievement and user.
   */
  public function generateImage(AchievementEntity $achievement, UserInterface $user, $image_type);

}
