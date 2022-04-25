<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Drupal\media\MediaInterface;

/**
 * Normalizer for a image field to show an image with image style.
 */
class MediaImageNormalizer implements NormalizerInterface {
  /**
   * The storage handler class for File.
   *
   * @var \Drupal\file\FileStorage
   */
  private $fileStorage;
  /**
   * The storage handler class for ImageStyle.
   *
   * @var \Drupal\image\ImageStyleStorage
   */
  private $imageStyleStorage;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity
   *   The Entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity) {
    $this->fileStorage = $entity->getStorage('file');
    $this->imageStyleStorage = $entity->getStorage('image_style');
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    $output = [];
    /** @var \Drupal\media\MediaInterface $data */
    $field_values = $data->toArray();
    if (!empty($field_values['field_media_image']) && !empty($field_values['field_media_image'][0]['target_id'])) {
      $image_style = $context['field_config']->getImageStyle();
      if (empty($image_style)) {
        $message = sprintf('The %s field does not have image_style property. Make sure it configured properly.', $context['field_config']->getId());
        throw new \Exception($message, 500);
      }
      $file = $this->fileStorage->load($field_values['field_media_image'][0]['target_id']);
      if ($file) {
        $output = [
          'UUID' => $file->uuid(),
          'filename' => $file->getFilename(),
          'url' => $this->imageStyleStorage->load($image_style)->buildUrl($file->getFileUri()),
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
    return $data instanceof MediaInterface;
  }

}
