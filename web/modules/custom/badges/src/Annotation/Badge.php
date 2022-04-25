<?php

namespace Drupal\badges\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a badge annotation object.
 *
 * @see \Drupal\badges\BadgePluginManager
 * @see \Drupal\badges\BadgePluginInterface
 * @see \Drupal\badges\BadgePluginBase
 * @see plugin_api
 *
 * @Annotation
 */
class Badge extends Plugin {

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
