<?php

namespace Drupal\badges;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages search backend plugins.
 *
 * @see \Drupal\badges\Annotation\Badge
 * @see \Drupal\badges\BadgePluginInterface
 * @see \Drupal\badges\BadgePluginBase
 * @see plugin_api
 */
class BadgePluginManager extends DefaultPluginManager {

  /**
   * Constructs a RecommendationEnginePluginManager object.
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
      'Plugin/Badge',
      $namespaces,
      $module_handler,
      'Drupal\badges\BadgePluginInterface',
      'Drupal\badges\Annotation\Badge'
    );
    $this->setCacheBackend($cache_backend, 'badge_info_plugins');
    $this->alterInfo('badge_info');
  }

}
