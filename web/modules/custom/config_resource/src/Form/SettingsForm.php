<?php

namespace Drupal\config_resource\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a setting UI for Configuration API.
 *
 * @package Drupal\config_resource\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'config_resource.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_resource_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $description = $this->t('One configuration name per line.<br />
Examples: <ul>
<li>user.settings</li>
</ul>');

    $config_api_settings = $this->config('config_resource.settings');
    $form['exposed_config_entities'] = [
      '#type' => 'textarea',
      '#rows' => 25,
      '#title' => $this->t('Configuration entity names to expose'),
      '#description' => $description,
      '#default_value' => implode(PHP_EOL, $config_api_settings->get('exposed_config_entities')),
      '#size' => 60,
    ];

    $form['exposed_config_entities_alters'] = [
      '#type' => 'textarea',
      '#rows' => 25,
      '#title' => $this->t('Alter normalized output of exposed config'),
      '#description' => $this->t('One config alter instruction on each line. <br/>Example: <ul><li>config.name:properity|function|argument</li></ul>'),
      '#default_value' => implode(PHP_EOL, $config_api_settings->get('exposed_config_entities_alters')),
      '#size' => 60,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config_api_settings = $this->config('config_resource.settings');
    $config_api_settings_array = preg_split("/[\r\n]+/", $values['exposed_config_entities']);
    $config_api_settings_array = array_filter($config_api_settings_array);
    $config_api_settings_array = array_values($config_api_settings_array);
    $config_api_settings->set('exposed_config_entities', $config_api_settings_array);
    $config_api_settings_array = preg_split("/[\r\n]+/", $values['exposed_config_entities_alters']);
    $config_api_settings_array = array_filter($config_api_settings_array);
    $config_api_settings_array = array_values($config_api_settings_array);
    $config_api_settings->set('exposed_config_entities_alters', $config_api_settings_array);
    $config_api_settings->save();
    parent::submitForm($form, $form_state);
  }

}
