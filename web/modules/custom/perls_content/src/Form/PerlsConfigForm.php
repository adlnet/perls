<?php

namespace Drupal\perls_content\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Custom settings form to allow tenant owners to manipulate specific config.
 */
class PerlsConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'system.site',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'perls_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#description' => $this->t('The system name appears in the browser title bar and in site reports.'),
      '#maxlength' => 128,
      '#size' => 60,
      '#default_value' => $this->config('system.site')->get('name'),
    ];

    $form['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#description' => $this->t("The From address in automated emails sent during registration and new password requests, and other notifications. (Use an address ending in your site's domain to help prevent this email being flagged as spam.)"),
      '#maxlength' => 128,
      '#size' => 60,
      '#default_value' => $this->config('system.site')->get('mail'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('system.site')
      ->set('name', $form_state->getValue('name'))
      ->set('mail', $form_state->getValue('mail'))
      ->save();
  }

}
