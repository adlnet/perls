<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\Core\Field\FieldItemList;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * This normalizer overrides the default response of FieldItemListNormalizer.
 *
 * In some cases the default NULL "response" isn't proper to us so we just
 * return back with 0.
 */
class ReturnEmptyValueWithZeroFieldItem implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $fieldDefinition = $object->getFieldDefinition();
    $cardinality = $fieldDefinition->getFieldStorageDefinition()->getCardinality();
    if ($cardinality !== 1) {
      return [];
    }
    switch ($fieldDefinition->getType()) {
      case 'boolean':
        return FALSE;

      default:
        return 0;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return ($data instanceof FieldItemList && $data->isEmpty());
  }

}
