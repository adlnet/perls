<?php

namespace Drupal\switches_additions\EntityNormalizer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Drupal\switches\Entity\SwitchInterface;

/**
 * Normalizer to turn an entity into it's rendered form.
 */
class SwitchNormalizer implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    if ($format !== 'json') {
      return $data;
    }
    $newData = [
      "status" => $data->getActivationStatus(),
    ];
    return $newData;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof SwitchInterface;
  }

}
