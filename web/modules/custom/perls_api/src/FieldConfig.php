<?php

namespace Drupal\perls_api;

use Drupal\entity_normalization\FieldConfig as EntityNormalizerFieldConfig;

/**
 * Extended entity_normalization FieldConfig class.
 */
class FieldConfig extends EntityNormalizerFieldConfig implements FieldConfigInterface {

  /**
   * {@inheritdoc}
   */
  public function getImageStyle() {
    return $this->definition['image_style'] ?: NULL;
  }

}
