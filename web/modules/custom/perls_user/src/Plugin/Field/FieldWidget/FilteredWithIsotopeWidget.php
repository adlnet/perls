<?php

namespace Drupal\perls_user\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Widget which filters entity reference items using isotope.js.
 *
 * @FieldWidget(
 *   id = "filtered_with_isotope_widget",
 *   label = @Translation("Filter with isotope.js"),
 *   field_types = {
 *     "entity_reference",
 *   },
 *   multiple_values = TRUE
 * )
 */
class FilteredWithIsotopeWidget extends OptionsWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'placeholder' => t('Search here...'),
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['placeholder'] = [
      '#type' => 'textfield',
      '#title' => 'Type in the placeholder for your field',
      '#default_value' => $this->getSetting('placeholder'),
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state
  ) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['wrapper'] = [
      '#theme' => 'perls_user_isotope_list',
      'checkboxes' => [
        '#type' => 'checkboxes',
        '#options' => $this->getOptions($items->getEntity()),
        '#default_value' => $this->getSelectedOptions($items),
      ],
      'filter_field' => [
        '#type' => 'textfield',
        '#placeholder' => $this->getSetting('placeholder'),
        '#attached' => [
          'library' => [
            'perls_user/isotope',
          ],
        ],
        '#attributes' => [
          'class' => ['iso-search-input'],
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateElement(array $element, FormStateInterface $form_state) {
    $element['#value'] = $element['wrapper']['checkboxes']['#value'];
    parent::validateElement($element, $form_state);
  }

}
