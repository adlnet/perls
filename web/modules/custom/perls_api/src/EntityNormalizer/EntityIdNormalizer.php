<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer to turn an entity into it's rendered form.
 */
class EntityIdNormalizer implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    return $data->id();
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof EntityInterface;
  }

}
