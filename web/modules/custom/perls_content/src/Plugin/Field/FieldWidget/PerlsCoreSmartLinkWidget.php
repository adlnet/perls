<?php

namespace Drupal\perls_content\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Plugin implementation of the 'perls_core_smart_link' widget.
 *
 * @FieldWidget(
 *   id = "perls_core_smart_link",
 *   label = @Translation("Perls Core Smart Link"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class PerlsCoreSmartLinkWidget extends LinkWidget {

  /**
   * {@inheritdoc}
   */
  public static function validateUriElement($element, FormStateInterface $form_state, $form) {
    $uri = static::getUserEnteredStringAsUri($element['#value']);

    // If getUserEnteredStringAsUri() mapped the entered value to a 'internal:'
    // URI , ensure the raw value begins with '/', '?' or '#'.
    // @todo '<front>' is valid input for BC reasons, may be removed by
    //   https://www.drupal.org/node/2421941
    if (parse_url($uri, PHP_URL_SCHEME) === 'internal'
      && !in_array($element['#value'][0], ['/', '?', '#'], TRUE)
      && substr($element['#value'], 0, 7) !== '<front>') {
      $uri = str_replace('internal:', 'https://', $uri);
      $form_state->setValueForElement($element, $uri);
      return;
    }
  }

}
