<?php

namespace Drupal\perls_recommendation;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages search backend plugins.
 *
 * @see \Drupal\perls_recommendation\Annotation\RecommendationEngine
 * @see \Drupal\perls_recommendation\RecommendationEnginePluginInterface
 * @see \Drupal\perls_recommendation\RecommendationEnginePluginBase
 * @see plugin_api
 */
class RecommendationEnginePluginManager extends DefaultPluginManager {

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
      'Plugin/RecommendationEngine',
      $namespaces,
      $module_handler,
      'Drupal\perls_recommendation\RecommendationEnginePluginInterface',
      'Drupal\perls_recommendation\Annotation\RecommendationEngine'
    );
    $this->setCacheBackend($cache_backend, 'recommendation_engine_info_plugins');
    $this->alterInfo('recommendation_engine_info');
  }

}
