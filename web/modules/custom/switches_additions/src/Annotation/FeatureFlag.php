<?php

namespace Drupal\switches_additions\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Feature Flag annotation object.
 *
 * @ingroup feature_flag_api
 *
 * @Annotation
 */
class FeatureFlag extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The ID of the switch to watch.
   *
   * @var string
   */
  public $switchId;

  /**
   * A list of hooks this feature flag supports.
   *
   * @var string[]
   */
  public $supportedManagerInvokeMethods;

  /**
   * A list of hooks this feature flag supports.
   *
   * @var int
   */
  public $weight = 0;

}
