<?php

namespace Drupal\perls_content_management\Routing;

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
    // Hide the help videos from webform.
    if ($route = $collection->get('webform.help')) {
      $route->setRequirement('_access', 'FALSE');
    }
    if ($route = $collection->get('webform.help.video')) {
      $route->setRequirement('_access', 'FALSE');
    }

    // Disable the filter/tips path.
    if ($route = $collection->get('filter.tips_all')) {
      $route->setRequirement('_access', 'FALSE');
    }
  }

}
