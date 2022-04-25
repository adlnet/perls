<?php

namespace Drupal\prompts\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Prompt annotation type for webform.
 *
 * @see \Drupal\prompts\Prompt\PromptManager
 * @Annotation
 */
class Prompt extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The id of a webform what the plugin use.
   *
   * @var string
   */
  public $webform;

  /**
   * The human-readable name of the prompt type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The description of prompt type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * How often the user get new prompt. The quantity is hour.
   *
   * @var int
   */
  public $limit;


  /**
   * Machine name of a webfrom field which contains the question.
   *
   * @var string
   */
  public $questionField;

}
