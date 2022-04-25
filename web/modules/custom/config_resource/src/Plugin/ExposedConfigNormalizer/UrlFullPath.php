<?php

namespace Drupal\config_resource\Plugin\ExposedConfigNormalizer;

use Drupal\config_resource\ExposedConfigNormalizerPluginBase;

/**
 * Defines a Delete Item plugin for exposed config.
 *
 * @ExposedConfigNormalizer(
 *   id = "url_full_path",
 *   label = @Translation("Convert URL to full path"),
 *   description = @Translation("Use this plugin to convert local uri to external urls.")
 * )
 */
class UrlFullPath extends ExposedConfigNormalizerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function process($item_key, array $data, array $arguments = []) {
    $uri = $this->getValue($item_key, $data);
    if (!empty($uri)) {
      $this->setValue($item_key, $data, file_create_url($uri));
    }

    return $data;
  }

}
