<?php

namespace Drupal\veracity_vql\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a VQL Post-process item annotation object.
 *
 * @see \Drupal\veracity_vql\Plugin\VqlPostProcessManager
 * @see plugin_api
 *
 * @Annotation
 */
class VqlPostProcess extends Plugin {


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

  /**
   * An array of context definitions describing the context used by the plugin.
   *
   * Plugin must implement \Drupal\Core\Plugin\ContextAwarePluginInterface.
   *
   * The array is keyed by context names.
   *
   * @var \Drupal\Core\Annotation\ContextDefinition[]
   */
  public $context_definitions = [];

}
