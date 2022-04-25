<?php

namespace Drupal\perls_adaptive_content\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Adaptive Content annotation object.
 *
 * @see \Drupal\perls_adaptive_content\AdaptiveContentPluginManager
 * @see \Drupal\perls_adaptive_content\AdaptiveContentPluginInterface
 * @see \Drupal\perls_adaptive_content\AdaptiveContentPluginBase
 * @see plugin_api
 *
 * @Annotation
 */
class AdaptiveContent extends Plugin {

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
