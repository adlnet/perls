<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\Core\Field\Plugin\Field\FieldType\NumericItemBase;
use Drupal\field\Entity\FieldConfig;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Print out the field value with suffix or/and prefix.
 */
class FieldValuePrefixSuffixNormalizer implements NormalizerInterface, DenormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $field_config = $object->getFieldDefinition()->getSettings();
    if (isset($field_config['suffix']) && isset($field_config['prefix'])) {
      return sprintf('%s%s%s', $field_config['prefix'], $object->getValue()['value'], $field_config['suffix']);
    }
    else {
      return $object->getValue();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $type, $format = NULL, array $context = []) {
    if (isset($context['drupal_field_name']) &&
    isset($context['entity_type']) &&
    isset($context['bundle'])) {
      $field_config = FieldConfig::loadByName($context['entity_type'], $context['bundle'], $context['drupal_field_name']);
      if ($field_config) {
        $settings = $field_config->getSettings();
        // Remove prefix.
        $data = str_replace($settings['prefix'], '', $data);
        // Remove suffix.
        $data = str_replace($settings['suffix'], '', $data);
        return $data;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof NumericItemBase;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return is_string($data);
  }

}
