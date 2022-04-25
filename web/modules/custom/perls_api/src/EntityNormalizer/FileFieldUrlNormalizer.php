<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\file\Entity\File;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Provides a normalizer for file field.
 */
class FileFieldUrlNormalizer implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $output = [];
    $file_data = $object->getValue();
    if (!empty($file_data) && $file_data['target_id']) {
      /** @var \Drupal\file\Entity\File $file */
      $file = File::load($file_data['target_id']);
      if ($file) {
        $output['id'] = $file->uuid();
        $output['name'] = $file->getFilename();
        $output['url'] = $file->createFileUrl(FALSE);
        $output['mimetype'] = $file->getMimeType();
      }
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof FileItem;
  }

}
