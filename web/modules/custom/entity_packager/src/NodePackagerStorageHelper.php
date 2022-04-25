<?php

namespace Drupal\entity_packager;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;

/**
 * A helper to manage entity package zips.
 */
class NodePackagerStorageHelper {

  /**
   * Drupal file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * OfflinePageHelper constructor.
   *
   * @param \Drupal\Core\File\FileSystem $file_system
   *   File system helper.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(FileSystem $file_system, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->fileSystem = $file_system;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Gives back the value of save_directory setting.
   *
   * @return string
   *   The value of setting.
   */
  public function getPackageDirectory() {
    $config = $this->configFactory->get('entity_packager.page_settings');
    return $config->get('save_directory');
  }

  /**
   * Load the entity package if it's exists.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A drupal entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Gives back an array of files which uri is belongs to.
   */
  public function getPackage(EntityInterface $entity) {
    $file_uri = $this->getPackageUri($entity);
    return $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $file_uri]);
  }

  /**
   * Delete a entity package file from CMS.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A Drupal entity.
   */
  public function deletePackage(EntityInterface $entity) {
    $files = $this->getPackage($entity);
    if (count($files)) {
      foreach ($files as $file) {
        /** @var \Drupal\file\Entity\File $file */
        $file->delete();
      }
    }
  }

  /**
   * Generates the uri of a zip file.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A drupal entity.
   *
   * @return string
   *   The generated uri for zip file.
   */
  public function getPackageUri(EntityInterface $entity) {
    return sprintf('%s/%s', $this->getPackageDirectory(), $this->getPackageName($entity));
  }

  /**
   * Generates the zip file name.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A drupal entity.
   *
   * @return string
   *   The zip file name.
   */
  public function getPackageName(EntityInterface $entity) {
    return sprintf('%s_%s.zip', $entity->getEntityTypeId(), $entity->id());
  }

}
