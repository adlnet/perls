<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer to turn an entity into it's rendered form.
 */
class TestAttemptNormalizer implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    // We need to get the last attempt and serialize it.
    if (!$object->hasField('field_test_attempts')) {
      return NULL;
    }

    $all_attempts = $object->field_test_attempts;
    if (!$all_attempts || $all_attempts->count() === 0) {
      return NULL;
    }
    // Load last attempt.
    $test_attempt = $all_attempts->get($all_attempts->count() - 1)->get('entity')->getTarget()->getValue();
    $serializer = \Drupal::service('serializer');
    return $serializer->normalize($test_attempt, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($object, $format = NULL) {
    return $object instanceof EntityInterface;
  }

}
