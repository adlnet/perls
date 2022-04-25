<?php

namespace Drupal\xapi\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\xapi\XapiContentException;
use Drupal\xapi\XapiContentFileHelper;

/**
 * Plugin implementation of the 'field_xapi_content_file_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "field_xapi_content_file_formatter",
 *   label = @Translation("xAPI File Formatter"),
 *   module = "xapi",
 *   field_types = {
 *     "field_xapi_content_file_item"
 *   }
 * )
 */
class XapiContentFileFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $uri = NULL;

      try {
        $uri = XapiContentFileHelper::getLaunchUrl($item);
      }
      catch (XapiContentException $exception) {
        \Drupal::logger('xapi')->warning('Failed to get launch URL: ' . $exception->getMessage());
        continue;
      }

      // At this point, each $item is a XapiContentFileItem.
      $elements[$delta] = [
        '#theme' => 'xapi_content_file_formatter',
        '#name' => $item->activity_name,
        '#id' => $item->activity_id,
        '#launch' => $uri,
        '#description' => $item->activity_description,
        '#file' => $item->entity,
        '#link' => NULL,
      ];
    }

    return $elements;
  }

}
