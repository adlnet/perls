<?php

namespace Drupal\perls_goals\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Config form of perls_goals module.
 */
class PerlsGoalsSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['perls_goals.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'perls_goals_settings_from';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['fields'] = [
      '#type' => 'textarea',
      '#maxlength' => 250,
      '#title' => $this->t('Field mapping'),
      '#description' => $this->t('This field contains a mapping between the goal field names and the respective API endpoint names. The type indicates the goal type. Integer values are checked daily. Average fields are checked weekly on Saturday.'),
      '#default_value' => $this->buildDefaultFieldMappingList(),
    ];

    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * Prepare the default value of fields mapping field.
   */
  protected function buildDefaultFieldMappingList() {
    $output = '';
    $current_field_list = $this->config('perls_goals.settings')->get('fields.field_values');
    if (!empty($current_field_list)) {
      foreach ($current_field_list as $field_mapping) {
        $output .= sprintf("%s|%s|%s|%s|%s\n", $field_mapping['drupal_field'], $field_mapping['api_field'], $field_mapping['stored_value_type'], $field_mapping['time_frame'], $field_mapping['goal_type']);
      }
    }

    return $output;
  }

}
