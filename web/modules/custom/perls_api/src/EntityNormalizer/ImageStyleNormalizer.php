<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer for a image field to show an image with image style.
 */
class ImageStyleNormalizer implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    $output = [];
    $image_style = $context['field_config']->getType();

    /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $data */
    $field_values = $data->getValue();
    if (!empty($field_values['target_id'])) {
      $file = File::load($field_values['target_id']);
      if ($file) {
        $output = [
          'UUID' => $file->uuid(),
          'name' => $file->getFilename(),
          'url' => ImageStyle::load($image_style)->buildUrl($file->getFileUri()),
          'original_url' => file_create_url($file->getFileUri()),
        ];
      }
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof ImageItem;
  }

}
