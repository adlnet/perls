<?php

namespace Drupal\veracity_vql\Plugin;

/**
 * Defines an interface for VQL Pre-process plugins.
 */
interface VqlPreProcessInterface extends VqlPluginInterface {

  /**
   * Alters the VQL query before it is sent to Veracity.
   *
   * @param array $query
   *   The query to alter.
   */
  public function alterQuery(array &$query);

}
