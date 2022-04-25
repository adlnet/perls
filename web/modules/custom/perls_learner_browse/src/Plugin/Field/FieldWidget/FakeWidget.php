<?php

namespace Drupal\perls_learner_browse\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A widget bar.
 *
 * @FieldWidget(
 *   id = "entity_browser_table",
 *   label = @Translation("Fake widget"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class FakeWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    return $form;
  }

}
