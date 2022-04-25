<?php

namespace Drupal\config_resource\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a ExposedConfigNormalizer Plugin annotation object.
 *
 * @see \Drupal\config_resource\ExposedConfigNormalizerPluginManager
 * @see \Drupal\config_resource\ExposedConfigNormalizerPluginInterface
 * @see \Drupal\config_resource\ExposedConfigNormalizerPluginBase
 * @see plugin_api
 *
 * @Annotation
 */
class ExposedConfigNormalizer extends Plugin {

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
