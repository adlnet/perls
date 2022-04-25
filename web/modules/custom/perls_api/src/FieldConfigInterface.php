<?php

namespace Drupal\perls_api;

use Drupal\entity_normalization\FieldConfigInterface as EntityNormalizerFieldConfigInterface;

/**
 * Extends the original FieldConfigInterface from entity_normalizer.
 */
interface FieldConfigInterface extends EntityNormalizerFieldConfigInterface {

  /**
   * Retrieves the image_style property for media types.
   *
   * @return string|null
   *   The value of the image_style property.
   */
  public function getImageStyle();

}
