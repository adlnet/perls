<?php

namespace Drupal\perls_podcast\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\NumberWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Custom number widget.
 *
 * @FieldWidget(
 *   id = "perls_podcast_number_widget",
 *   label = @Translation("Perls Podcast number widget"),
 *   field_types = {
 *     "integer"
 *   }
 * )
 */
class PerlsPodcastNumberWidget extends NumberWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $value = isset($items[$delta]->value) ? $items[$delta]->value : NULL;
    if (NULL !== $value) {
      $formatted_time = gmdate("G:i:s", $value);
      $element['value']['#default_value'] = $formatted_time;
      $element['value']['#placeholder'] = $formatted_time;
    }
    $element['value']['#attributes']['readonly'] = TRUE;
    $element['value']['#attributes']['class'][] = 'read-only-input';

    return $element;
  }

}
