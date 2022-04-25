<?php

namespace Drupal\xapi\Plugin\Field\FieldType;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Archiver\ArchiverInterface;
use Drupal\Core\Archiver\ArchiverManager;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\xapi\XapiContentException;
use Drupal\Core\Entity\EntityInterface;

/**
 * Plugin implementation of the 'field_xapi_content_file_item' field type.
 *
 * @FieldType(
 *   id = "field_xapi_content_file_item",
 *   label = @Translation("xAPI File Item"),
 *   module = "xapi",
 *   description = @Translation("This field stores the ID of an xAPI content file as an integer value."),
 *   category = @Translation("Reference"),
 *   default_widget = "field_xapi_content_file_widget",
 *   default_formatter = "field_xapi_content_file_formatter",
 *   list_class = "\Drupal\file\Plugin\Field\FieldType\FileFieldItemList",
 *   constraints = {"ReferenceAccess" = {}, "FileValidation" = {}}
 * )
 */
class XapiContentFileItem extends FileItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $settings = parent::defaultStorageSettings();
    // We want to keep 'target_type' and 'uri_scheme' but lose the rest.
    unset($settings['display_field']);
    unset($settings['display_default']);
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    // We want the default 'file_directory' and 'max_filesize'.
    $settings = parent::defaultFieldSettings();

    // We don't need a file extensions setting, it's always zip.
    unset($settings['file_extensions']);

    // We don't want a description field (we get that from tincan.xml)
    unset($settings['description_field']);

    // Allow setting the description as required or not.
    $settings['description_required'] = 0;

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'target_id' => [
          'description' => 'The ID of the file entity.',
          'type' => 'int',
          'unsigned' => TRUE,
        ],
        'activity_id' => [
          'description' => 'A unique ID for the activity.',
          'type' => 'varchar',
          'length' => 128,
        ],
        'activity_launch_path' => [
          'description' => 'The launch path of the activity.',
          'type' => 'varchar',
          'length' => 256,
          'not null' => TRUE,
        ],
        'activity_name' => [
          'description' => 'A name for the activity.',
          'type' => 'varchar',
          'length' => 512,
          'not null' => TRUE,
        ],
        'activity_description' => [
          'description' => 'A description for the activity.',
          'type' => 'varchar',
          'length' => 1024,
        ],
      ],
      'indexes' => [
        'target_id' => ['target_id'],
      ],
      'foreign keys' => [
        'target_id' => [
          'table' => 'file_managed',
          'columns' => ['target_id' => 'fid'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    // We don't want to use the default "display":
    // And "description" fields of a file item.
    unset($properties['display']);
    unset($properties['description']);

    // Note that we are required to add to:
    // Properties for each value defined in the schema.
    $properties['activity_id'] = DataDefinition::create('string')
      ->setLabel(t('ID'))
      ->setDescription(t('A unique ID for the activity.'));

    $properties['activity_launch_path'] = DataDefinition::create('string')
      ->setLabel(t('Launch path'))
      ->setDescription(t('The launch path of the activity.'));

    $properties['activity_name'] = DataDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('A name for the activity.'));

    $properties['activity_description'] = DataDefinition::create('string')
      ->setLabel(t('Description'))
      ->setDescription(t('A description for the activity.'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);

    // We keep uri_scheme (from FileItem)
    // And target_type (from EntityReferenceItem)
    // But drop the rest.
    unset($element['display_field']);
    unset($element['display_default']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    // We can't just defer to parent and unset:
    // Unused elements because the parent expects element keys:
    // That don't exist because we unset them in defaultFieldSettings.
    $element = [];
    $settings = $this->getSettings();

    $element['file_directory'] = [
      '#type' => 'textfield',
      '#title' => t('File directory'),
      '#default_value' => $settings['file_directory'],
      '#description' => t('Optional subdirectory within the upload destination where files will be stored. Do not include preceding or trailing slashes.'),
      '#element_validate' => [[get_class($this), 'validateDirectory']],
      '#weight' => 3,
    ];

    $element['max_filesize'] = [
      '#type' => 'textfield',
      '#title' => t('Maximum upload size'),
      '#default_value' => $settings['max_filesize'],
      '#description' => t('Enter a value like "512" (bytes), "80 KB" (kilobytes) or "50 MB" (megabytes) in order to restrict the allowed file size. If left empty the file sizes will be limited only by PHP\'s maximum post and file upload sizes (current limit <strong>%limit</strong>).', ['%limit' => format_size(Environment::getUploadMaxSize())]),
      '#size' => 10,
      '#element_validate' => [[get_class($this), 'validateMaxFilesize']],
      '#weight' => 5,
    ];

    $element['description_required'] = [
      '#type' => 'checkbox',
      '#title' => t('Description required'),
      '#default_value' => $settings['description_required'],
      '#description' => t('Set whether or not a description is required for uploaded eLearning content.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();

    try {
      $this->updateItemFields();
    }
    catch (XapiContentException $exception) {
      // Generally this shouldn't happen as we validate on upload.
      \Drupal::logger('xapi')->warning('Presave error: ' . $exception->getMessage());
    }
  }

  /**
   * Update the fields on this item that require data to be read from the.
   *
   * File entity.
   *
   * This also unzips the file; it's possible this method is overloaded.
   *
   * @throws \Drupal\xapi\XapiContentException
   *   If updating items fail for any reason.
   */
  public function updateItemFields() {
    if (empty($this->entity) || !$this->entity instanceof EntityInterface) {
      throw new XapiContentException('Missing file with ID ' . $this->target_id);
    }

    $data = NULL;

    // Attempt to set fields from tincan.xml file.
    // Generally we only set fields if we don't already have data in them,
    // To avoid wiping user-provided data.
    try {
      $data = read_metadata_from_uri($this->entity->getFileUri());
    }
    catch (XapiContentException $exception) {
      throw $exception;
    }

    $new_id = $data['id'];

    if (empty($this->activity_id) && !empty($new_id)) {
      $this->activity_id = $new_id;
    }

    $new_name = $data['name'];

    if (empty($this->activity_name) && !empty($new_name)) {
      $this->activity_name = $new_name;
    }

    $new_description = $data['description'];

    if (empty($this->activity_description) && !empty($new_description)) {
      $this->activity_description = $new_description;
    }

    $new_launch = $data['launch'];

    if (empty($this->activity_launch_path) && !empty($new_launch)) {
      $this->activity_launch_path = $new_launch;
    }

    // Unzipping here ensures that the package is ready when "Launch" appears.
    // The unzip method is a noop if the package seems to already be unzipped.
    $this->unzipPackage();
  }

  /**
   * Unzip this file item and store it on the server.
   *
   * @throws \Drupal\xapi\XapiContentException
   *   If we couldn't get a real path, or creating a directory failed.
   */
  public function unzipPackage() {
    // Get path to zip file.
    $file_service = \Drupal::service('file_system');

    if (!$file_service instanceof FileSystemInterface) {
      throw new XapiContentException('Failed to access file service.');
    }

    $location = $file_service->realpath($this->getEntityUri());

    if (empty($location)) {
      throw new XapiContentException('Unable to get path to entity URI.');
    }

    // Get path to unzip destination.
    $destination = $file_service->realpath($this->getUnzipDestinationUri());

    if (empty($destination)) {
      throw new XapiContentException('Unable to get destination for URI: ' . $destination);
    }

    // If the directory already exists, we assume we've already unzipped.
    // We do this because updateItemFields gets called a lot on upload.
    // For large files, this causes noticeable slowdown in the site.
    $exists = \Drupal::service('file_system')->prepareDirectory($destination);

    if ($exists) {
      return;
    }

    // If not, ensure directory exists.
    $result = \Drupal::service('file_system')->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY);

    if (!$result) {
      throw new XapiContentException('Failed to create directory: ' . $destination);
    }

    // The unzipped file is deleted in `xapi_file_predelete`.
    $archiver_service = \Drupal::service('plugin.manager.archiver');

    if (!$archiver_service instanceof ArchiverManager) {
      throw new XapiContentException('Failed to access archive service.');
    }

    $zip_instance = $archiver_service->getInstance(['filepath' => $location]);

    if (!$zip_instance instanceof ArchiverInterface) {
      throw new XapiContentException('Failed to access ZIP archive interface');
    }

    $zip_instance->extract($destination);

    // Verify that files exist in the directory.
    if (count(glob($destination . '/*')) === 0) {
      throw new XapiContentException('Failed to unzip ZIP archive, or ZIP archive was empty.');
    }
  }

  /**
   * Retrieves a URL to the activity.
   *
   * This URI does _not_ contain query parameters for passing
   * LRS connection information.
   *
   * @return string
   *   A public URL where the activity can be accessed.
   *
   * @throws \Drupal\xapi\XapiContentException
   *   If the unzip URI or activity launch path are invalid.
   */
  public function getActivityUri() {
    $uri = $this->getUnzipDestinationUri();
    $path = $this->activity_launch_path;

    if (empty($path)) {
      throw new XapiContentException('Invalid activity launch path: ' . $path);
    }

    // Is there a better way to form a URI?
    // What if $uri ends with a slash? Or $path begins with one?
    $totalUri = $uri . '/' . $path;

    if (parse_url($totalUri) === FALSE) {
      throw new XapiContentException('Invalid combined launch path: ' . $totalUri);
    }

    $url = file_create_url($totalUri);

    if (empty($url)) {
      throw new XapiContentException('Unable to create file URL for URI: ' . $totalUri);
    }

    return $url;
  }

  /**
   * Retrieves the URI where the content package will be unzipped.
   *
   * @return string
   *   The URI of the unzipped content package.
   *
   * @throws \Drupal\xapi\XapiContentException
   *   If the associated entity is invalid or returns an invalid URI.
   */
  private function getUnzipDestinationUri() {
    return unzip_destination_uri_for_entity_uri($this->getEntityUri());
  }

  /**
   * Validates and returns the file URI associated with this object's entity.
   *
   * @return string
   *   The URI of the file entity.
   *
   * @throws \Drupal\xapi\XapiContentException
   *   If the associated entity is invalid or returns an invalid URI.
   */
  private function getEntityUri() {
    $entity = $this->entity;

    if (!$entity instanceof File) {
      throw new XapiContentException('Invalid entity for file item.');
    }

    $uri = $entity->getFileUri();

    if (parse_url($uri) === FALSE) {
      throw new XapiContentException('Invalid URI: ' . $uri);
    }

    return $uri;
  }

}
