<?php

namespace Drupal\config_resource;

/**
 * Defines a ExposedConfigNormalizer Plugin Interface.
 *
 * @see \Drupal\config_resource\ExposedConfigNormalizerPluginManager
 * @see \Drupal\config_resource\ExposedConfigNormalizerPluginInterface
 * @see \Drupal\config_resource\ExposedConfigNormalizerPluginBase
 * @see plugin_api
 */
interface ExposedConfigNormalizerPluginInterface {

  /**
   * Process config array to updated output.
   *
   * @param string $item_key
   *   A colon seperated key map of item to update.
   * @param array $data
   *   The config data to alter.
   * @param array $arguments
   *   An optional array of arguments for the function.
   *
   * @return array
   *   The input array with selected item altered.
   */
  public function process($item_key, array $data, array $arguments = []);

}
