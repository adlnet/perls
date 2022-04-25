<?php

namespace Drupal\veracity_vql\Plugin;

/**
 * Base class for VQL Pre-process plugins.
 */
abstract class VqlPreProcessBase extends VqlPluginBase implements VqlPreProcessInterface {

  /**
   * {@inheritdoc}
   */
  abstract public function alterQuery(array &$query);

}
