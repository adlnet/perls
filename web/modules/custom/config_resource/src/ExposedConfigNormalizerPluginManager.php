<?php

namespace Drupal\config_resource;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Manages exposed config plugins.
 *
 * @see \Drupal\config_resource\Annotation\ExposedConfigNormalizer
 * @see \Drupal\config_resource\ExposedConfigNormalizerPluginInterface
 * @see \Drupal\config_resource\ExposedConfigNormalizerPluginBase
 * @see plugin_api
 */
class ExposedConfigNormalizerPluginManager extends DefaultPluginManager {
  use StringTranslationTrait;

  /**
   * Constructs a ExposedConfigNormalizerPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
      \Traversable $namespaces,
      CacheBackendInterface $cache_backend,
      ModuleHandlerInterface $module_handler
    ) {
    parent::__construct(
      'Plugin/ExposedConfigNormalizer',
      $namespaces,
      $module_handler,
      'Drupal\config_resource\ExposedConfigNormalizerPluginInterface',
      'Drupal\config_resource\Annotation\ExposedConfigNormalizer'
    );
    $this->setCacheBackend($cache_backend, 'exposed_config_normalizer_plugin');
    $this->alterInfo('exposed_config_normalizer_info');
  }

  /**
   * Return a list of all plugins keyed by name.
   */
  public function getPlugins() {
    $plugins = [];
    foreach ($this->getDefinitions() as $name => $plugin_definition) {
      if (class_exists($plugin_definition['class'])) {
        $plugin = $this->createInstance($name);
        if ($plugin) {
          $plugins[$name] = $plugin;
        }
      }
    }
    return $plugins;
  }

}
