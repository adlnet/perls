<?php

namespace Drupal\perls_user\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Custom access handler to Settings theme route.
 */
class ThemeSettingsSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {

    if ($route = $collection->get('system.theme_settings_theme')) {
      $route->setRequirement('_permission', 'administer perls theme');
      $route->setRequirement('_custom_access', '\Drupal\perls_user\Access\ThemeSettingsAccessCheck::access');
    }

  }

}
