<?php

namespace Drupal\notifications_goals\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * User can configure the module settings.
 */
class GoalNotificationsSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['notifications_goals.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'notifications_goals_settings_from';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $goal_fields = $this->configFactory->get('perls_goals.settings')->get('fields.field_values');
    $messages = $this->config('notifications_goals.settings')->get('messages');
    if (!empty($goal_fields)) {
      foreach ($goal_fields as $field) {
        $form["{$field['api_field']}_message"] = [
          '#type' => 'textarea',
          '#maxlength' => 250,
          // @codingStandardsIgnoreStart
          '#title' => $this->t($field['api_field'] . ' notification message'),
          // @codingStandardsIgnoreEnd
          '#description' => $this->t("Configure the notification message. This message will be sent when a user approaches their goal. A token called @count is always available for use in the message. @count will display the number of interactions needed to reach the goal."),
          '#default_value' => !empty($messages[$field['api_field']]) ? $messages[$field['api_field']] : '',
        ];
      }
      $form['all'] = [
        '#type' => 'textarea',
        '#maxlength' => 250,
        '#title' => $this->t('All goal achieved message'),
        '#default_value' => !empty($messages['all']) ? $messages['all'] : '',
      ];
      $form['none'] = [
        '#type' => 'textarea',
        '#maxlength' => 250,
        '#title' => $this->t('No goal set message'),
        '#default_value' => !empty($messages['none']) ? $messages['none'] : '',
      ];
    }

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('fields')) {
      $form_state->setValue('fields', trim($form_state->getValue('fields')));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config_notification = $this->configFactory->getEditable('notifications_goals.settings');
    $goal_fields = $this->configFactory->get('perls_goals.settings')->get('fields.field_values');
    $messages = [];
    if (!empty($goal_fields)) {
      foreach ($goal_fields as $field) {
        $message_field_value = $form_state->getValue($field['api_field'] . '_message');
        if (isset($message_field_value)) {
          $messages[$field['api_field']] = $message_field_value;
        }
      }

      if ($form_state->getValue('all')) {
        $messages['all'] = $form_state->getValue('all');
      }

      if ($form_state->getValue('none')) {
        $messages['none'] = $form_state->getValue('none');
      }
    }
    $config_notification->set('messages', $messages);
    $config_notification->save();
    parent::submitForm($form, $form_state);
  }

}
