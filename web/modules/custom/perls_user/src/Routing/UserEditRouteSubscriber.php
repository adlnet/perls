<?php

namespace Drupal\perls_user\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Set the user edit page title.
 */
class UserEditRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.user.edit_form')) {
      $defaults = $route->getDefaults();
      unset($defaults['_title_callback']);
      $defaults['_title'] = 'Account Settings';
      $route->setDefaults($defaults);
    }
  }

}
