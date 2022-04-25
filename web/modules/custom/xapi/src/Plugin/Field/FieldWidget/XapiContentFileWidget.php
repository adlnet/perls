<?php

namespace Drupal\xapi\Plugin\Field\FieldWidget;

use Drupal\file\Plugin\Field\FieldWidget\FileWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\xapi\Plugin\Field\FieldType\XapiContentFileItem;
use Drupal\xapi\XapiContentException;

/**
 * Plugin implementation of the 'field_xapi_content_file_widget' widget.
 *
 * @FieldWidget(
 *   id = "field_xapi_content_file_widget",
 *   label = @Translation("xAPI File Widget"),
 *   field_types = {
 *     "field_xapi_content_file_item"
 *   }
 * )
 */
class XapiContentFileWidget extends FileWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Only support zip extension.
    $element['#upload_validators']['file_validate_extensions'] = ['zip'];

    // Add a custom validator.
    $element['#upload_validators']['file_validate_xapi_content'] = [];

    // Only allow zip mime type files.
    $element['#accept'] = 'application/zip';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    // This happens if the user just removed a file.
    // It seems like we can safely bail in that case?
    if (empty($element['#files'])) {
      return $element;
    }

    $file = reset($element['#files']);
    $data = NULL;

    try {
      $uri = $file->getFileUri();
      $data = read_metadata_from_uri($uri);
    }
    catch (\Exception $exception) {
      // We can't set an error on the form here because it may be validated.
      \Drupal::logger('xapi')->warning('Process error: ' . $exception->getMessage());
      return $element;
    }

    $element['activity_name'] = [
      '#type' => 'textfield',
      '#title' => t('Activity name'),
      '#default_value' => isset($item['activity_name']) ? $item['activity_name'] : $data['name'],
      '#description' => t('The name of the activity.'),
      // @todo get max length from the item config.
      '#maxlength' => 512,
      '#required' => TRUE,
      '#weight' => -3,
    ];

    // @todo require this field if the field setting to require it is enabled.
    $element['activity_description'] = [
      '#type' => 'textfield',
      '#title' => t('Activity description'),
      '#default_value' => isset($item['activity_description']) ? $item['activity_description'] : $data['description'],
      '#description' => t('The description of the activity.'),
      '#maxlength' => 1024,
      '#weight' => -2,
    ];

    $element['advanced_info'] = [
      '#type' => 'details',
      '#title' => t('Package Info'),
      '#open' => FALSE,
      '#weight' => 1,
    ];

    $id = isset($item['activity_id']) ? $item['activity_id'] : $data['id'];

    // @todo we should be validating these fields, at some point.
    $element['advanced_info']['activity_id'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => t('<strong>Activity ID</strong>: @id', ['@id' => empty($id) ? t('None') : $id]),
      '#description' => t('A unique ID for the activity.'),
    ];

    $launch = isset($item['activity_launch_path']) ? $item['activity_launch_path'] : $data['launch'];

    if (!empty($launch)) {
      $element['advanced_info']['activity_launch_path'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => t('<strong>Activity launch path</strong>: @launch', ['@launch' => $launch]),
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    parent::extractFormValues($items, $form, $form_state);

    // Never update item fields when removing a package.
    $clicked_button = end($form_state->getTriggeringElement()['#parents']);

    if ($clicked_button === 'remove_button') {
      return;
    }

    foreach ($items as $delta => $item) {
      // All items *should* be xAPI content, but check just in case.
      if (!$item instanceof XapiContentFileItem) {
        continue;
      }

      try {
        $item->updateItemFields();
      }
      catch (XapiContentException $exception) {
        \Drupal::logger('xapi')->warning('Error when extracting form values: ' . $exception->getMessage());
      }
    }
  }

}
