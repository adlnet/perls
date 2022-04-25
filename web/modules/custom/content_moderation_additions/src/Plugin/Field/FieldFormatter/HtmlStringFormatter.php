<?php

namespace Drupal\content_moderation_additions\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'html_string' formatter.
 *
 * @FieldFormatter(
 *   id = "html_string",
 *   label = @Translation("Raw text(No escaping)"),
 *   description = @Translation("Renders raw text without HTML escaping, avoid using with user-entered data."),
 *   field_types = {
 *     "string_long"
 *   },
 *   quickedit = {
 *     "editor" = "plain_text"
 *   }
 * )
 */
class HtmlStringFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      // The field type of revision log message's is string_long, in some cases
      // it has HTML tags in it. This avoids the HTML tags in the output.
      $elements[$delta] = [
        '#type' => 'inline_template',
        '#template' => '{{ value|raw }}',
        '#context' => ['value' => Xss::filter($item->value)],
      ];
    }

    return $elements;
  }

}
