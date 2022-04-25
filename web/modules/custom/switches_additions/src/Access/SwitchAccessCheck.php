<?php

namespace Drupal\switches_additions\Access;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\switches_additions\FeatureFlagPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom access based on switches.
 */
class SwitchAccessCheck implements AccessInterface {

  /**
   * The Feature Flag Plugin Manager.
   *
   * @var \Drupal\switches_additions\FeatureFlagPluginManager
   */
  protected $featureFlagPluginManager;

  /**
   * Constructs a SwitchAccessCheck object.
   *
   * @param \Drupal\switches_additions\FeatureFlagPluginManager $feature_flag_plugin_manager
   *   The permission handler.
   */
  public function __construct(FeatureFlagPluginManager $feature_flag_plugin_manager) {
    $this->featureFlagPluginManager = $feature_flag_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.switches_additions.feature_flag')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, RouteMatchInterface $route_match) {
    $args = func_get_args();
    return $this->featureFlagPluginManager->invokeRouteAccessForPlugins('viewAccess', $args);
  }

}
