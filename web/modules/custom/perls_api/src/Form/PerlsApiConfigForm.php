<?php

namespace Drupal\perls_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Config form where you can set module related settings.
 */
class PerlsApiConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'perls_api.settings',
      'entity_packager.page_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'perls_api_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('perls_api.settings');
    $form['user_agent'] = [
      '#required' => TRUE,
      '#type' => 'textfield',
      '#title' => $this->t('User-agent'),
      '#description' => $this->t('It should match with User-agent what the app is using.'),
      '#default_value' => $config->get('user_agent'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('perls_api.settings')
      ->set('user_agent', $form_state->getValue('user_agent'))
      ->save();

    // Update the user-agent under entity packager.
    $this->config('entity_packager.page_settings')
      ->set('user_agent', $form_state->getValue('user_agent'))
      ->save();
  }

}
