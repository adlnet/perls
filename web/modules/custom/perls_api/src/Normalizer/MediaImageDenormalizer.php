<?php

namespace Drupal\perls_api\Normalizer;

use Drupal\Component\Datetime\Time;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\TypedData\Plugin\DataType\IntegerData;
use Drupal\serialization\Normalizer\FieldItemNormalizer;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Saves media entity and converts array into media image target id.
 */
class MediaImageDenormalizer extends FieldItemNormalizer implements DenormalizerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The "file_system" service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Drupal time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * Construct a Media image denormalizer.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The normalizer for tokens.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   File system.
   * @param \Drupal\Component\Datetime\Time $time
   *   Drupal time service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              ModuleHandlerInterface $module_handler,
                              FileSystemInterface $file_system,
                              Time $time) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->fileSystem = $file_system;
    $this->time = $time;
  }

  /**
   * Denormalizes the image media fields.
   *
   * @inheritDoc
   *
   * @throws \Exception
   */
  public function denormalize($data, $type, $format = NULL, array $context = []) {
    $definition = $context['target_instance']->getFieldDefinition()
      ->getItemDefinition();
    $settings = $definition->getSettings();
    if (!isset($settings['handler_settings']['target_bundles'])) {
      return $data;
    }
    $bundle = reset($settings['handler_settings']['target_bundles']);
    // Denormalize only for image media field.
    if (isset($settings['target_type']) && $settings['target_type'] == 'media' && $bundle === 'image') {
      $file = system_retrieve_file($data, NULL, TRUE);
      if ($file !== FALSE) {
        $filename = $file->getFilename();

        // Create parent directory.
        $time = $this->time->getRequestTime();
        $directory = date('Y', $time) . "-" . date('m', $time);
        $temp_file_path = $file->getFileUri();
        $destination = str_replace($filename, $directory, $temp_file_path);
        if ($this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY)) {
          try {
            // Move file to destination path.
            $file_destination = sprintf("%s/%s", $destination, $filename);
            $uri = $this->fileSystem->move($temp_file_path, $file_destination);
            $file->setFileUri($uri);
            $file->save();
          }
          catch (FileException $e) {
            throw new HttpException(500, 'Temporary file could not be moved to file location');
          }

          try {
            // Save media entity.
            /** @var \Drupal\media\MediaTypeInterface $mediaType */
            $mediaType = $this->entityTypeManager
              ->getStorage('media_type')
              ->load($bundle);

            $name = Unicode::truncate($filename, 255, TRUE);
            /** @var \Drupal\media\Entity\Media[] $media */
            $media = $this->entityTypeManager->getStorage('media')
              ->create([
                'bundle' => $bundle,
                'uid' => \Drupal::currentUser()->id(),
                'status' => TRUE,
                $mediaType->getSource()->getConfiguration()['source_field'] => [
                  'target_id' => $file->id(),
                  'alt' => $filename,
                ],
              ]);
            $media->setName($name)->setPublished(TRUE)->save();
            return $media->id();
          }
          catch (\Exception $e) {
            throw new EntityStorageException("Error occurred while saving the image");
          }
        }
      }
      else {
        throw new \Exception("The image file could not be retrieved.");
      }
    }
    // Returned the un-modified $data if it is not a media field.
    return $data;
  }

  /**
   * Supports de-normalization only if the expected value is integer.
   *
   * @inheritDoc
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return $type === IntegerData::class;
  }

  /**
   * Disable normalization.
   *
   * @inheritDoc
   */
  public function supportsNormalization($data, $format = NULL) {
    return FALSE;
  }

}
