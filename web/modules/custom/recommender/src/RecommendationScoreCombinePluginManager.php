<?php

namespace Drupal\recommender;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages score combine plugins.
 *
 * @see \Drupal\recommender\Annotation\RecommendationScoreCombinePlugin
 * @see \Drupal\recommender\RecommendationScoreCombinePluginInterface
 * @see \Drupal\recommender\RecommendationScoreCombinePluginBase
 * @see plugin_api
 */
class RecommendationScoreCombinePluginManager extends DefaultPluginManager {

  /**
   * Constructs a RecommendationScoreCombinePluginManager object.
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
      'Plugin/RecommendationScoreCombine',
      $namespaces,
      $module_handler,
      'Drupal\recommender\RecommendationScoreCombinePluginInterface',
      'Drupal\recommender\Annotation\RecommendationScoreCombinePlugin'
    );
    $this->setCacheBackend($cache_backend, 'recommender_score_combine_info_plugins');
    $this->alterInfo('recommender_score_combine_info');
  }

}
