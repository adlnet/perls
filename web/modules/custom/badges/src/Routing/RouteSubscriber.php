<?php

namespace Drupal\badges\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Limit user achievements view to admin.
    if ($route = $collection->get('achievements.achievements_controller_userAchievements')) {
      $route->setRequirement('_permission', 'manually grant achievements');
    }
  }

}
