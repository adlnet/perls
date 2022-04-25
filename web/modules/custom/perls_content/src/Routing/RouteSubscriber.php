<?php

namespace Drupal\perls_content\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteSubscriber.
 *
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Enable specific routes to the perls admin.
    $routes = [
      'admin_toolbar_tools.flush',
      'system.site_maintenance_mode',
      'system.status',
      'system.run_cron',
      'dblog.overview',
      'dblog.event',
    ];

    foreach ($routes as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setRequirement('_permission', 'administer perls');
      }
    }
  }

}
