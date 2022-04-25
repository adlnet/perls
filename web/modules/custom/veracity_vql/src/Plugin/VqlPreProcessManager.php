<?php

namespace Drupal\veracity_vql\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;

/**
 * Provides the VQL Pre-process plugin manager.
 */
class VqlPreProcessManager extends DefaultPluginManager {

  use VqlPluginManagerTrait;

  /**
   * Constructs a new VqlPreProcessManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/VqlPreProcess', $namespaces, $module_handler, 'Drupal\veracity_vql\Plugin\VqlPreProcessInterface', 'Drupal\veracity_vql\Annotation\VqlPreProcess');

    $this->alterInfo('veracity_vql_vql_pre_process_info');
    $this->setCacheBackend($cache_backend, 'veracity_vql_vql_pre_process_plugins');
  }

  /**
   * Alters the VQL query.
   *
   * @param string $query
   *   The query to alter.
   * @param array $configuration
   *   Plugin configuration keyed by plugin id.
   * @param \Drupal\Component\Plugin\Context\ContextInterface[] $contexts
   *   An array of contexts for the plugins.
   *
   * @return string
   *   The altered query.
   */
  public function alterQuery(string $query, array $configuration, array $contexts): string {
    $plugins = $this->getPlugins($configuration, FALSE);
    if (empty($plugins)) {
      return $query;
    }

    $vql = json_decode($query, TRUE);

    foreach ($plugins as $plugin) {
      if ($plugin instanceof ContextAwarePluginInterface) {
        foreach ($plugin->getContextDefinitions() as $name => $definition) {
          if (isset($contexts[$name])) {
            $plugin->setContext($name, $contexts[$name]);
          }
        }
      }
      $plugin->alterQuery($vql);
    }

    return json_encode($vql);
  }

}
