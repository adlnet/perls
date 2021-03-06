<?php

namespace Drupal\recommender\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for User recommendation status entities.
 */
class UserRecommendationStatusViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.
    return $data;
  }

}
