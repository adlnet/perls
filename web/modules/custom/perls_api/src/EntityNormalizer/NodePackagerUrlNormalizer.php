<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\node\Entity\Node;
use Drupal\entity_packager\NodePackagerStorageHelper;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Help to extend the endpoint with entity package url.
 */
class NodePackagerUrlNormalizer implements NormalizerInterface {

  /**
   * Offline storage helper service.
   *
   * @var \Drupal\entity_packager\NodePackagerStorageHelper
   */
  protected $nodePackagerStorage;

  /**
   * OfflinePageZipNormalizer constructor..
   *
   * @param \Drupal\entity_packager\NodePackagerStorageHelper $helper
   *   Offline helper service.
   */
  public function __construct(NodePackagerStorageHelper $helper) {
    $this->nodePackagerStorage = $helper;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $output = NULL;
    $files = $this->nodePackagerStorage->getPackage($object);
    if (!empty($files)) {
      /** @var \Drupal\file\Entity\File $file */
      $file = reset($files);
      if (file_exists($file->getFileUri())) {
        $output = [
          'id' => $file->uuid(),
          'name' => $file->getFilename(),
          'url' => file_create_url($file->getFileUri()),
        ];
      }
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof Node;
  }

}
