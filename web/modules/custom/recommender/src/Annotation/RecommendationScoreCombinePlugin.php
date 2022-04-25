<?php

namespace Drupal\recommender\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Recommendation Score Combine annotation object.
 *
 * @see \Drupal\recommender\RecommendationScoreCombinePluginManager
 * @see \Drupal\recommender\RecommendationScoreCombinePluginInterface
 * @see \Drupal\recommender\RecommendationScoreCombinePluginBase
 * @see plugin_api
 *
 * @Annotation
 */
class RecommendationScoreCombinePlugin extends Plugin {

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

}
