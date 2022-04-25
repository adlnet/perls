<?php

namespace Drupal\media_unsplash\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example settings for this site.
 */
class UnsplashApiConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const SETTINGS = 'media_unsplash.admin.config';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'unsplash_api_key_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $form['media_unsplash_app_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unsplash App Name'),
      '#default_value' => $config->get('media_unsplash_app_name'),
      '#required' => TRUE,
    ];
    $form['media_unsplash_access_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unsplash access key'),
      '#default_value' => $config->get('media_unsplash_access_key'),
      '#description' => $this->t('Register on Unsplash.com and get your API keys <a href="https://unsplash.com/developers" target="_blank">here</a>.'),
      '#required' => TRUE,
    ];
    $form['media_unsplash_secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unsplash secret key'),
      '#default_value' => $config->get('media_unsplash_secret_key'),
      '#required' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('media_unsplash_app_name', $form_state->getValue('media_unsplash_app_name'))
      ->save();
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('media_unsplash_access_key', $form_state->getValue('media_unsplash_access_key'))
      ->save();
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('media_unsplash_secret_key', $form_state->getValue('media_unsplash_secret_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
