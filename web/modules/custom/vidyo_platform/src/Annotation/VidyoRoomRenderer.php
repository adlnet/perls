<?php

namespace Drupal\vidyo_platform\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Vidyo Room Renderer item annotation object.
 *
 * @see \Drupal\vidyo_platform\Plugin\VidyoRoomRendererPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class VidyoRoomRenderer extends Plugin {


  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
