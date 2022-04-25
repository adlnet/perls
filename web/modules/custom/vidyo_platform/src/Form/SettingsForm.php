<?php

namespace Drupal\vidyo_platform\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\vidyo_platform\Api\Client\UserServiceClient;

/**
 * Settings form for configuring the Vidyo API connection.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'vidyo_platform.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vidyo_platform_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('vidyo_platform.settings');
    $form['portal_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Portal URL'),
      '#required' => TRUE,
      '#default_value' => $config->get('portal_url'),
      '#placeholder' => 'https://tenant.platform.vidyo.io',
    ];

    $form['portal_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#required' => TRUE,
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('portal_username'),
    ];

    $form['portal_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('portal_password'),
    ];

    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#attributes' => [
        'class' => ['button', 'button--danger'],
      ],
      '#submit' => ['::resetForm'],
      '#limit_validation_errors' => [],
      '#weight' => 1,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Try and connect to the Vidyo API.
    try {
      $base_url = rtrim(trim($form_state->getValue('portal_url')), '/');
      $username = trim($form_state->getValue('portal_username'));
      $password = $form_state->getValue('portal_password');

      $client = new UserServiceClient($base_url, $username, $password);
      $client->getPortalVersion();

      // Reset the form values in case we transformed them at all.
      $form_state->setValue('portal_url', $base_url);
      $form_state->setValue('portal_username', $username);
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('portal_url', $this->t('Error connecting to VidyoPortal: %error', ['%error' => $e->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('vidyo_platform.settings')
      ->set('portal_url', $form_state->getValue('portal_url'))
      ->set('portal_username', $form_state->getValue('portal_username'))
      ->set('portal_password', $form_state->getValue('portal_password'))
      ->save();
  }

  /**
   * Removes VidyoPortal API connection settings.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('The configuration has been reset.'));
    $this->config('vidyo_platform.settings')->delete();
  }

}
