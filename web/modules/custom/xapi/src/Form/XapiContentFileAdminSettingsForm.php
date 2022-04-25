<?php

namespace Drupal\xapi\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\xapi\XapiActorIFIManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure xAPI content settings for this site.
 */
class XapiContentFileAdminSettingsForm extends ConfigFormBase {

  /**
   * The xapi actor ifi manager.
   *
   * @var \Drupal\xapi\XapiActorIFIManager
   */
  protected $ifiManager;

  /**
   * XapiContentFileAdminSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\xapi\XapiActorIFIManager $ifi_manager
   *   Plugin manager to handle xapi actor ifi types.
   */
  public function __construct(ConfigFactoryInterface $config_factory, XapiActorIFIManager $ifi_manager) {
    parent::__construct($config_factory);
    $this->ifiManager = $ifi_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.xapi_actor_ifi')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xapi_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['xapi.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory()->get('xapi.settings');
    // Data from the database.
    $raw_data = $this->configFactory()->get('xapi.settings')->getRawData();

    // Invalidate raw data if any fields are empty.
    if (empty($raw_data) || empty($raw_data['lrs_url']) || empty($raw_data['lrs_username']) || empty($raw_data['lrs_password'])) {
      $raw_data = NULL;
    }

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('LRS server'),
      '#description' => $this->t('<strong>Changing the LRS endpoint may cause data loss for your learners unless you have already synced statements and document stores with your new LRS.</strong>'),
      '#open' => TRUE,
    ];

    // First try to load the values from database.
    // Then move to config overrides and at the end to environment variable.
    $form['general']['lrs_url'] = [
      '#default_value' => (!empty($raw_data)) ? $raw_data['lrs_url'] : $config->get('lrs_url'),
      '#description' => $this->t('The URL of the LRS endpoint.'),
      '#maxlength' => 512,
      '#placeholder' => 'https://www.example.com',
      '#required' => TRUE,
      '#size' => 80,
      '#title' => $this->t('LRS Endpoint'),
      '#type' => 'textfield',
    ];

    $form['general']['lrs_username'] = [
      '#default_value' => (!empty($raw_data)) ? $raw_data['lrs_username'] : $config->get('lrs_username'),
      '#description' => $this->t('The username or public key for the LRS endpoint.'),
      '#maxlength' => 512,
      '#placeholder' => 'username',
      '#required' => TRUE,
      '#size' => 40,
      '#title' => $this->t('LRS Username'),
      '#type' => 'textfield',
    ];

    $form['general']['lrs_password'] = [
      '#default_value' => (!empty($raw_data)) ? $raw_data['lrs_password'] : $config->get('lrs_password'),
      '#description' => $this->t('The password or private key for the LRS endpoint.'),
      '#maxlength' => 256,
      '#placeholder' => 'password',
      '#required' => TRUE,
      '#size' => 40,
      '#title' => $this->t('LRS Password'),
      '#type' => 'textfield',
    ];

    $form['xapi'] = [
      '#type' => 'details',
      '#title' => $this->t('xAPI statement settings'),
      '#open' => TRUE,
    ];

    $form['xapi']['warning'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['messages', 'messages--warning'],
      ],
      '#value' => $this->t('Changing the Inverse Functional Identifier (IFI) can result in data loss.'),
    ];

    $form['xapi']['real_name'] = [
      '#type' => 'checkbox',
      '#default_value' => $config->get('real_name') ?? FALSE,
      '#title' => $this->t("Include user's full name"),
      '#description' => $this->t("When enabled, the user's full name from their profile will be included in the actor object on the statement."),
    ];

    $form['xapi']['xapi_actor_ifi'] = [
      '#type' => 'radios',
      '#default_value' => $config->get('xapi_actor_ifi') ?? XapiActorIFIManager::DEFAULT_IFI_TYPE,
      '#title' => $this->t('Inverse functional identifier (IFI)'),
      '#description' => $this->t('Select how actors should be uniquely identified; for more information, see the %xapi_doc</a>.', [
        '%xapi_doc' => Link::fromTextAndUrl(t('xAPI documentation'), Url::fromUri('https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#inversefunctional', [
          'attributes' => ['target' => '_blank'],
        ]))->toString(),
      ]),
      '#options' => $this->ifiManager->getOptions(),
    ];

    $form = parent::buildForm($form, $form_state);

    // Hide the reset button if LRS environment variables are not set.
    if (getenv('LRS_HOST') !== FALSE && getenv('LRS_USERNAME') !== FALSE && getenv('LRS_PASSWORD') !== FALSE) {
      $reset_url = new Url('xapi.admin_settings_reset_form');
      $form['actions']['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Reset to system default'),
        '#attributes' => [
          'class' => ['button', 'button--danger'],
        ],
        '#url' => $reset_url,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if (!filter_var($form_state->getValue('lrs_url'), FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('lrs_url', $this->t('Provide a valid URL for the LRS endpoint.'));
    }
    // Make sure url ends in /.
    $url = $form_state->getValue('lrs_url');
    if (strpos(strrev($url), '/') !== 0) {
      $url = $url . '/';
      $form_state->setValue('lrs_url', $url);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('xapi.settings');
    $config
      ->set('lrs_url', $form_state->getValue('lrs_url'))
      ->set('lrs_username', $form_state->getValue('lrs_username'))
      ->set('lrs_password', $form_state->getValue('lrs_password'))
      ->set('real_name', $form_state->getValue('real_name'))
      ->set('xapi_actor_ifi', $form_state->getValue('xapi_actor_ifi'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
