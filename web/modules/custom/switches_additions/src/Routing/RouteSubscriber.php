<?php

namespace Drupal\switches_additions\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\switches_additions\FeatureFlagPluginManager;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * Feature flag manager.
   *
   * @var \Drupal\switches_additions\FeatureFlagPluginManager
   */
  protected $featureFlagManager;

  /**
   * Route subscriber for feature flag switcher.
   *
   * @param \Drupal\switches_additions\FeatureFlagPluginManager $feature_flag_manager
   *   The feature flag plugin manager.
   */
  public function __construct(FeatureFlagPluginManager $feature_flag_manager) {
    $this->featureFlagManager = $feature_flag_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $switch_routes = $this->featureFlagManager->getRouteList();
    foreach ($switch_routes as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setRequirement('_switch_access_check', 'TRUE');
      }
    }
  }

}
