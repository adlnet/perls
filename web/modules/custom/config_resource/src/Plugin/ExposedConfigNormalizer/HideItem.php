<?php

namespace Drupal\config_resource\Plugin\ExposedConfigNormalizer;

use Drupal\config_resource\ExposedConfigNormalizerPluginBase;

/**
 * Defines a Delete Item plugin for exposed config.
 *
 * @ExposedConfigNormalizer(
 *   id = "hide_item",
 *   label = @Translation("Remove item from response"),
 *   description = @Translation("Use this plugin to remove items from the exposed config reponse.")
 * )
 */
class HideItem extends ExposedConfigNormalizerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function process($item_key, array $data, array $arguments = []) {
    $this->deleteValue($item_key, $data);
    return $data;
  }

}
