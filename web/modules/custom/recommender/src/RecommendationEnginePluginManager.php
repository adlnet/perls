<?php

namespace Drupal\recommender;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Manages search backend plugins.
 *
 * @see \Drupal\recommender\Annotation\RecommendationEngine
 * @see \Drupal\recommender\RecommendationEnginePluginInterface
 * @see \Drupal\recommender\RecommendationEnginePluginBase
 * @see plugin_api
 */
class RecommendationEnginePluginManager extends DefaultPluginManager {
  use StringTranslationTrait;

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
      'Plugin/Recommendations',
      $namespaces,
      $module_handler,
      'Drupal\recommender\RecommendationEnginePluginInterface',
      'Drupal\recommender\Annotation\RecommendationEnginePlugin'
    );
    $this->setCacheBackend($cache_backend, 'recommender_engine_info_plugins');
    $this->alterInfo('recommender_engine_info');
  }

  /**
   * Retrieves information about the available recommendation stages.
   *
   * These are then used by recommendation plugins in their "stages" definition
   * to specify in which stages they will run.
   *
   * @return array
   *   An associative array mapping stage identifiers to information about that
   *   stage. The information itself is an associative array with the following
   *   keys:
   *   - label: The translated label for this stage.
   */
  public function getRecommendationStages() {
    return [
      RecommendationEnginePluginInterface::STAGE_GENERATE_CANDIDATES => [
        'label' => $this->t('Generate Candidates'),
      ],
      RecommendationEnginePluginInterface::STAGE_ALTER_CANDIDATES => [
        'label' => $this->t('Alter Candidates'),
      ],
      RecommendationEnginePluginInterface::STAGE_SCORE_CANDIDATES => [
        'label' => $this->t('Score Candidates'),
      ],
      RecommendationEnginePluginInterface::STAGE_RERANK_CANDIDATES => [
        'label' => $this->t('Rerank Candidates'),
      ],
    ];
  }

}
