<?php

namespace Drupal\perls_user_management\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the account cancel form route.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    if ($route = $collection->get('entity.user.cancel_form')) {
      $requirements = $route->getRequirements();
      unset($requirements['_entity_access']);
      $requirements['_cancel_own_account'] = 'TRUE';
      $route->setRequirements($requirements);
    }
  }

}
