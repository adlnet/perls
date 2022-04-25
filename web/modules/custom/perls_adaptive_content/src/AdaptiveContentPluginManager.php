<?php

namespace Drupal\perls_adaptive_content;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages adaptive content plugins.
 *
 * @see \Drupal\perls_adaptive_content\Annotation\AdaptiveContent
 * @see \Drupal\perls_adaptive_content\AdaptiveContentPluginInterface
 * @see \Drupal\perls_adaptive_content\AdaptiveContentPluginBase
 * @see plugin_api
 */
class AdaptiveContentPluginManager extends DefaultPluginManager {

  /**
   * Constructs a AdaptiveContentPluginManager object.
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
      'Plugin/AdaptiveContent',
      $namespaces,
      $module_handler,
      'Drupal\perls_adaptive_content\AdaptiveContentPluginInterface',
      'Drupal\perls_adaptive_content\Annotation\AdaptiveContent'
    );
    $this->setCacheBackend($cache_backend, 'perls_adaptive_content_adaptive_content_plugins');
    $this->alterInfo('perls_adaptive_content_adaptive_content_info');
  }

}
