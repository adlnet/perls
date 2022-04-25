<?php

namespace Drupal\xapi\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\xapi\Plugin\Field\FieldType\XapiContentFileItem;

/**
 * Plugin implementation of the 'field_xapi_activity_id_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "field_xapi_activity_id_formatter",
 *   label = @Translation("xAPI Activity ID Formatter"),
 *   module = "xapi",
 *   field_types = {
 *     "field_xapi_content_file_item"
 *   }
 * )
 */
class XapiActivityIdFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    if (empty($items)) {
      return $elements;
    }

    foreach ($items as $delta => $item) {
      if (!$item instanceof XapiContentFileItem) {
        continue;
      }

      $id = $item->activity_id;

      if (empty($id)) {
        continue;
      }

      $elements[$delta] = [
        '#markup' => $id,
      ];
    }

    return $elements;
  }

}
