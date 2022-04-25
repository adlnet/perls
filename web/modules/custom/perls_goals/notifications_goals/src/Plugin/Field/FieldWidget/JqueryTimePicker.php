<?php

namespace Drupal\notifications_goals\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A jquery time picker widget.
 *
 * @FieldWidget(
 *   id = "time_picker",
 *   label = @Translation("jQuery time picker"),
 *   field_types = {
 *     "time"
 *   }
 * )
 */
class JqueryTimePicker extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'step' => 1,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [
      'step' => [
        '#type' => 'textfield',
        '#title' => $this->t('Step to change seconds'),
        '#open' => TRUE,
        '#default_value' => $this->getSetting('step'),
      ],
    ] + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $additional = [
      '#type' => 'textfield',
      '#size' => 10,
      '#default_value' => isset($items[$delta]->value) ? \Drupal::service('date.formatter')->format($items[$delta]->value, 'custom', 'g:i a') : '',
      '#attached' => [
        'library' => [
          'notifications_goals/notifications_goals.timefield',
        ],
        'drupalSettings' => [
          'pushNotificationTimeField' => [
            'step' => $this->getSetting('step'),
          ],
        ],
      ],
      '#attributes' => [
        'class' => ['timepicker'],
      ],
    ];
    $element['value'] = $element + $additional;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $element_values = [];
    foreach ($values as $delta => $value) {
      if ($value['value'] !== '') {
        $element_values[$delta] = strtotime($value['value']) - strtotime('today', \Drupal::service('datetime.time')->getRequestTime());
      }
    }

    return $element_values;
  }

}
