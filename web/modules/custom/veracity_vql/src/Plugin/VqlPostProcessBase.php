<?php

namespace Drupal\veracity_vql\Plugin;

/**
 * Base class for VQL Post-process plugins.
 */
abstract class VqlPostProcessBase extends VqlPluginBase implements VqlPostProcessInterface {

  /**
   * {@inheritdoc}
   */
  abstract public function processResult(array &$result);

}
