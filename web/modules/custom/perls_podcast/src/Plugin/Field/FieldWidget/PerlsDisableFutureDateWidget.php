<?php

namespace Drupal\perls_podcast\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldWidget\DateTimeDefaultWidget;

/**
 * Disables future dates on browser's datepicker.
 *
 * @FieldWidget(
 *   id = "perls_podcast_disable_futura_date_widget",
 *   label = @Translation("Perls Podcast Disable Future Date"),
 *   field_types = {
 *     "datetime"
 *   }
 * )
 */
class PerlsDisableFutureDateWidget extends DateTimeDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['value']['#attributes']['max'] = date('Y-m-d');

    return $element;
  }

}
