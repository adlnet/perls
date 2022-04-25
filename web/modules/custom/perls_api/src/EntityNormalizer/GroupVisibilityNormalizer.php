<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\perls_group_management\Permissions\GroupVisibilityPermission;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer for group visibility.
 */
class GroupVisibilityNormalizer implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    $statuses = [
      GroupVisibilityPermission::PUBLIC_GROUP => 'public',
      GroupVisibilityPermission::PRIVATE_GROUP => 'private',
    ];

    $value = $this->getValue($data);
    return $statuses[$value] ?? 'unknown';
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $this->getValue($data) !== NULL;
  }

  /**
   * Retrieves the integer value of the passed in data.
   *
   * @param mixed $data
   *   The data to normalize.
   *
   * @return int|null
   *   The integer value, or null if there was no value.
   */
  protected function getValue($data) {
    if ($data instanceof TypedDataInterface && is_numeric($data->value)) {
      return (int) $data->value;
    }

    return NULL;
  }

}
