<?php

namespace Drupal\perls_api\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteSubscriber.
 *
 * Implements a RouteSubscriber which sets the 'access jsonapi' permission
 * to be required on all jsonapi routes.
 *
 * @package Drupal\perls_api\Routing
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * De 'access jsonapi' permission is nodig om jsonapi-routes te bekijken.
   *
   * {@inheritDoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    foreach ($collection as $key => $route) {
      if (stripos($key, 'jsonapi') !== 0) {
        continue;
      }

      $permission = $route->getRequirement('_permission');
      if (!empty($permission)) {
        $permission .= ',access jsonapi';
      }
      else {
        $permission = 'access jsonapi';
      }
      $route->setRequirement('_permission', $permission);
    }
  }

}
