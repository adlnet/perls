<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\Core\Field\Plugin\Field\FieldType\IntegerItem;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalize a number from 1 - 1000 to 0 - 1.
 */
class PrecentageToFractionNormalizer implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    return $data->getValue()['value'] / 100;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof IntegerItem;
  }

}
