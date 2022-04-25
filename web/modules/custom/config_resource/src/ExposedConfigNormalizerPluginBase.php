<?php

namespace Drupal\config_resource;

/**
 * Defines a ExposedConfigNormalizer Plugin Base.
 *
 * @see \Drupal\config_resource\ExposedConfigNormalizerPluginManager
 * @see \Drupal\config_resource\ExposedConfigNormalizerPluginInterface
 * @see \Drupal\config_resource\ExposedConfigNormalizerPluginBase
 * @see plugin_api
 */
abstract class ExposedConfigNormalizerPluginBase implements ExposedConfigNormalizerPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function process($item_key, array $data, array $arguments = []) {
    return $data;
  }

  /**
   * Get value of item at key.
   */
  protected function getValue($item_key, &$data) {
    $temp = &$data;
    foreach (explode(':', $item_key) as $key) {
      if (!isset($temp[$key])) {
        return NULL;
      }
      $temp = &$temp[$key];
    }
    return $temp;
  }

  /**
   * Set value of item at key.
   */
  protected function setValue($item_key, &$data, $value) {
    $temp = &$data;
    foreach (explode(':', $item_key) as $key) {
      if (!isset($temp[$key])) {
        $temp[$key] = '';
      }
      $temp = &$temp[$key];
    }
    $temp = $value;
    return $data;
  }

  /**
   * Delete data item at key.
   */
  protected function deleteValue($item_key, &$data) {
    $keys = explode(':', $item_key, 2);
    if (!isset($data[$keys[0]])) {
      return FALSE;
    }
    if (count($keys) == 1) {
      unset($data[$keys[0]]);
      return TRUE;
    }
    return $this->deleteValue($keys[1], $data[$keys[0]]);

  }

}
