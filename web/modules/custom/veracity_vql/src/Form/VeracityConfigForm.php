<?php

namespace Drupal\veracity_vql\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\veracity_vql\VeracityApiInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration for Veracity integration.
 */
class VeracityConfigForm extends ConfigFormBase {

  use StringTranslationTrait;

  /**
   * The Veracity API service.
   *
   * @var \Drupal\veracity_vql\VeracityApiInterface
   */
  protected $veracityApi;

  /**
   * Creates a new configuration form.
   */
  public function __construct(ConfigFactoryInterface $config_factory, VeracityApiInterface $veracity_api) {
    $this->setConfigFactory($config_factory);
    $this->veracityApi = $veracity_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('veracity_vql.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'veracity_vql.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'veracity_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('veracity_vql.settings');
    $form['endpoint'] = [
      '#type' => 'url',
      '#required' => TRUE,
      '#title' => $this->t('Veracity URL'),
      '#description' => $this->t('URL to the Veracity xAPI endpoint.'),
      '#default_value' => $config->get('endpoint'),
      '#placeholder' => empty($config->get('endpoint')) ? $this->veracityApi->getEndpoint() : NULL,
    ];
    $form['access_key_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Access Key'),
      '#description' => $this->t('The username for the access key; ensure this key has <strong>Advanced Queries</strong> enabled.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('access_key_id'),
    ];
    $form['access_key_secret'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Access Key Secret'),
      '#description' => $this->t('The password for the Veracity access key.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('access_key_secret'),
      '#attributes' => ['autocomplete' => 'off'],
    ];
    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Revert to Default'),
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
    // Ensure that the entered endpoint and credentials work.
    $endpoint = $form_state->getValue('endpoint');
    $access_key = [
      $form_state->getValue('access_key_id'),
      $form_state->getValue('access_key_secret'),
    ];

    try {
      $this->veracityApi->testConnection($endpoint, $access_key);
    }
    catch (\Exception $e) {
      $form_state->setError($form['endpoint'], $this->t('Error testing connection: %error', ['%error' => $e->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('veracity_vql.settings')
      ->set('endpoint', $form_state->getValue('endpoint'))
      ->set('access_key_id', $form_state->getValue('access_key_id'))
      ->set('access_key_secret', $form_state->getValue('access_key_secret'))
      ->save();
  }

  /**
   * Removes custom Veracity settings.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $this->config('veracity_vql.settings')->delete();
  }

}
