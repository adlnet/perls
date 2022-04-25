<?php

namespace Drupal\switches_additions;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin type manager for feature flags.
 *
 * @ingroup feature_flag_api
 */
class FeatureFlagPluginManager extends DefaultPluginManager {

  /**
   * Constructs a FeatureFlagPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/FeatureFlag', $namespaces, $module_handler, 'Drupal\switches_additions\FeatureFlagPluginInterface', 'Drupal\switches_additions\Annotation\FeatureFlag');

    $this->setCacheBackend($cache_backend, 'feature_flag_plugins');
    $this->alterInfo('feature_flag_info');
  }

  /**
   * {@inheritdoc}
   */
  protected function setCachedDefinitions($definitions) {
    // Sort by weight.
    uasort($definitions, function ($a, $b) {
      $a = $a['weight'] ?? 0;
      $b = $b['weight'] ?? 0;

      if ($a === $b) {
        return 0;
      }
      return ($a < $b) ? -1 : 1;
    });
    parent::setCachedDefinitions($definitions);
  }

  /**
   * Gets plugins for specific switch_id.
   */
  public function getPluginsForSwitch($switch) {
    $switch_id = $switch->id();
    $definitions = $this->getDefinitions();
    return array_reduce($definitions, function ($result, $definition) use ($switch_id) {
      if ($definition['switchId'] === $switch_id) {
        array_push($result, $this->createInstance($definition['id']));
      }
      return $result;
    }, []);
  }

  /**
   * Invokes given method in all plugins.
   */
  public function invokeFunctionForPlugins($method_name, $args) {
    $result = NULL;
    $definitions = $this->getDefinitions();
    foreach ($definitions as $definition) {
      if (!in_array($method_name, $definition['supportedManagerInvokeMethods'])) {
        continue;
      }
      $plugin = $this->createInstance($definition['id']);
      // This will return the last result (if any).
      // Note you can change the weight of the plugin so it returns last.
      $result = call_user_func_array([$plugin, $method_name], $args);
    }
    return $result;
  }

  /**
   * Invokes given access method in all plugins.
   *
   * This differs from the generic invokeFunctionForPlugins by
   * apply drupal default logic when more than one entity check
   * is returned.
   */
  public function invokeAccessFunctionForPlugins($method_name, $args) {
    $result = AccessResult::neutral();
    $definitions = $this->getDefinitions();
    foreach ($definitions as $definition) {
      if (!in_array($method_name, $definition['supportedManagerInvokeMethods'])) {
        continue;
      }
      $plugin = $this->createInstance($definition['id']);
      // This uses drupal orIf logic which is default for access checks.
      $result = $result->orIf(call_user_func_array(
        [
          $plugin,
          $method_name,
        ],
        $args));
    }
    return $result;
  }

  /**
   * Collect those routes where we need to do custom access check.
   */
  public function getRouteList() {
    $result = [];
    $definitions = $this->getDefinitions();
    foreach ($definitions as $definition) {
      if (!in_array('getSwitchFeatureRoutes', $definition['supportedManagerInvokeMethods'])) {
        continue;
      }
      $plugin = $this->createInstance($definition['id']);
      // This will return the last result (if any).
      // Note you can change the weight of the plugin so it returns last.
      $result = array_merge($result, call_user_func_array([
        $plugin,
        'getSwitchFeatureRoutes',
      ], []));
    }

    return $result;
  }

  /**
   * Help to managed the route access functions.
   */
  public function invokeRouteAccessForPlugins($method_name, $args) {
    $definitions = $this->getDefinitions();
    $access = AccessResult::forbidden();
    foreach ($definitions as $definition) {
      if (!in_array($method_name, $definition['supportedManagerInvokeMethods'])) {
        continue;
      }
      $plugin = $this->createInstance($definition['id']);
      /** @var \Drupal\Core\Access\AccessResult $access */
      $access = call_user_func_array([$plugin, $method_name], $args);
      // We handle differently the allowed case because most of cases Drupal
      // doesn't take different between neutral and forbidden. So, in the first
      // case when an allowed occur we finish the run, otherwise there is a huge
      // chance the next run will override it.
      if ($access->isAllowed()) {
        return $access;
      }
    }
    return $access;
  }

  /**
   * Invokes functions when switch is toggled.
   */
  public function switchWasToggled($switch) {
    $plugins = $this->getPluginsForSwitch($switch);
    foreach ($plugins as $plugin) {
      $plugin->featureWasToggled();
    }
  }

}
