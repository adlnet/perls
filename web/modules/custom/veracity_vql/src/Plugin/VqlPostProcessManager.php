<?php

namespace Drupal\veracity_vql\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;

/**
 * Provides the VQL Post-process plugin manager.
 */
class VqlPostProcessManager extends DefaultPluginManager {

  use VqlPluginManagerTrait;

  /**
   * Constructs a new VqlPostProcessManager object.
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
    parent::__construct('Plugin/VqlPostProcess', $namespaces, $module_handler, 'Drupal\veracity_vql\Plugin\VqlPostProcessInterface', 'Drupal\veracity_vql\Annotation\VqlPostProcess');

    $this->alterInfo('veracity_vql_vql_post_process_info');
    $this->setCacheBackend($cache_backend, 'veracity_vql_vql_post_process_plugins');
  }

  /**
   * Processes a VQL result using configured plugins.
   *
   * @param array $result
   *   The result to process/alter.
   * @param array $configuration
   *   Plugin configuration keyed by plugin id.
   * @param \Drupal\Component\Plugin\Context\ContextInterface[] $contexts
   *   An array of contexts for the plugins.
   */
  public function processResult(array &$result, array $configuration, array $contexts) {
    $plugins = $this->getPlugins($configuration, FALSE);
    if (empty($plugins)) {
      return;
    }

    foreach ($plugins as $plugin) {
      if ($plugin instanceof ContextAwarePluginInterface) {
        foreach ($plugin->getContextDefinitions() as $name => $definition) {
          if (isset($contexts[$name])) {
            $plugin->setContext($name, $contexts[$name]);
          }
        }
      }

      $plugin->processResult($result);
    }
  }

}
