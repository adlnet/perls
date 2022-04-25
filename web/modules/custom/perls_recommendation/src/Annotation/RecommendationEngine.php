<?php

namespace Drupal\perls_recommendation\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a RecommendationEngine annotation object.
 *
 * @see \Drupal\perls_recommendation\RecommendationEnginePluginManager
 * @see \Drupal\perls_recommendation\RecommendationEnginePluginInterface
 * @see \Drupal\perls_recommendation\RecommendationEnginePluginBase
 * @see plugin_api
 *
 * @Annotation
 */
class RecommendationEngine extends Plugin {

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
