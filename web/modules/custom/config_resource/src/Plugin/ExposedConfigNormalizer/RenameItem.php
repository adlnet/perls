<?php

namespace Drupal\config_resource\Plugin\ExposedConfigNormalizer;

use Drupal\config_resource\ExposedConfigNormalizerPluginBase;

/**
 * Defines a Delete Item plugin for exposed config.
 *
 * @ExposedConfigNormalizer(
 *   id = "rename_item",
 *   label = @Translation("Convert URL to full path"),
 *   description = @Translation("Use this plugin to convert local uri to external urls.")
 * )
 */
class RenameItem extends ExposedConfigNormalizerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function process($item_key, array $data, array $arguments = []) {
    // We need one argument for the new name (full context).
    if (empty($arguments)) {
      return $data;
    }
    $this->setValue($arguments[0], $data, $this->getValue($item_key, $data));
    $this->deleteValue($item_key, $data);
    return $data;
  }

}
