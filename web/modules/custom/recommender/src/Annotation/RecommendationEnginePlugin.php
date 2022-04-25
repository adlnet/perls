<?php

namespace Drupal\recommender\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Recommendation Plugin annotation object.
 *
 * @see \Drupal\recommender\RecommendationEnginePluginManager
 * @see \Drupal\recommender\RecommendationEnginePluginInterface
 * @see \Drupal\recommender\RecommendationEnginePluginBase
 * @see plugin_api
 *
 * @Annotation
 */
class RecommendationEnginePlugin extends Plugin {

  /**
   * The backend plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the backend plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The backend description.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The stages this plugin will run in, along with their default weights.
   *
   * This is represented as an associateive array, mapping one or more of the
   * stage identifiers to the default weight for that stage. For a list of
   * available stages see
   * \Drupal\recommender\RecommendationEnginePluginManager::getRecommendationStages().
   *
   * @var int[]
   */
  public $stages;

}
