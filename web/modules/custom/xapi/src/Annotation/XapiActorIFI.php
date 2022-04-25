<?php

namespace Drupal\xapi\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a XapiActorIFI annotation object.
 *
 * @Annotation
 */
class XapiActorIFI extends Plugin {

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
