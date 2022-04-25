<?php

namespace Drupal\veracity_vql\Plugin;

/**
 * Defines an interface for VQL Post-process plugins.
 */
interface VqlPostProcessInterface extends VqlPluginInterface {

  /**
   * Processes or alters the VQL result.
   *
   * @param array $result
   *   The VQL result.
   */
  public function processResult(array &$result);

}
