<?php

namespace Drupal\externalauth_additions\Form;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\State;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\externalauth_additions\SSOConfigurationManager;
use SimpleSAML\Auth\Source;
use SimpleSAML\Module\saml\Auth\Source\SP;
use SimpleSAML\Utils\XML;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides additional saml login settings.
 */
class SSOconfigForm extends FormBase {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * Drupal\externalauth_additions\SSOConfigurationManager definition.
   *
   * @var \Drupal\externalauth_additions\SSOConfigurationManager
   */
  protected $ssoConfiguration;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\State\State $state
   *   The state service.
   * @param \Drupal\externalauth_additions\SSOConfigurationManager $sso_configuration
   *   The SSO configuration manager.
   */
  public function __construct(FileSystem $fileSystem, EntityTypeManager $entityTypeManager, State $state, SSOConfigurationManager $sso_configuration) {
    $this->fileSystem = $fileSystem;
    $this->entityTypeManager = $entityTypeManager;
    $this->state = $state;
    $this->ssoConfiguration = $sso_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('entity_type.manager'),
      $container->get('state'),
      $container->get('externalauth_additions.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'externalauth_additions_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $site_name = [
      '@site' => $this->config('system.site')->get('name'),
    ];

    $form['description'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['page-description']],
    ];

    $form['description']['content'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => t('@site can integrate with any SAML 2.0 Identity Provider (IdP) for authenticating users. By default, users will be identified by their email addresses. Enabling this integration adds a new option on the login screen to sign in using the identity provider. When single sign-on is required, users will automatically be redirected to the identity provider.',
      $site_name),
    ];

    $source_id = $this->ssoConfig()->get('auth_source');
    $source = Source::getById($source_id);
    if ($source instanceof SP) {
      $metadata = $source->getHostedMetadata();
      $endpoint = reset($metadata['AssertionConsumerService']);
      $form['acs_url'] = [
        '#type' => 'item',
        '#title' => $this->t('ACS URL'),
        '#description' => $this->t('Assertion Consumer Service (ACS) URL'),
        '#markup' => $endpoint['Location'],
      ];
    }
    $form['entity_id'] = [
      '#type' => 'item',
      '#title' => $this->t('Entity ID'),
      '#description' => $this->t('The unique name for the connection with your IdP (Identity Provider). If you need a custom entity ID, please contact support.'),
      '#markup' => $source_id,
    ];
    $element = [
      '#type' => 'html_tag',
      '#tag' => 'a',
      '#value' => $this->t('Download'),
      '#attributes' => [
        'href' => Url::fromRoute('externalauth_additions.download_metadata')->toString(),
        'class' => ['o-button--small'],
      ],
    ];

    $form['metadata_download'] = [
      '#type' => 'item',
      '#title' => $this->t('SP Metadata'),
      '#description' => $this->t('Your identity provider will ask for metadata to create a connection with @site.', $site_name),
      '#markup' => render($element),
    ];

    $table = $this->attributeTable();
    $form['attributes'] = [
      '#type' => 'item',
      '#markup' => render($table),
    ];

    $form['enable_saml_login'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable single sign-on (SSO)'),
      '#description' => $this->t('@site supports using SAML 2.0 to allow your users to sign on with your existing identity provider.', $site_name),
      '#default_value' => $this->ssoConfiguration->isEnabled(),
    ];

    $fid = $this->state->get('externalauth_additions_uploaded_xml_file');
    $form['upload_metadata'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('SAML metadata.xml'),
      '#description' => $this->t('Upload the metadata received from your identity provider. <em>This is different than the file you downloaded above.</em>'),
      '#required' => TRUE,
      '#upload_location' => 'private://saml_config/',
      '#upload_validators' => [
        'file_validate_extensions' => ['xml'],
        'file_validate_size' => [Environment::getUploadMaxSize()],
      ],
      '#default_value' => [($fid ? $fid : 0)],
    ];

    $form['force_saml_login'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require single sign-on'),
      '#description' => $this->t('Always redirect your users to your identity provider.<br><strong>Warning:</strong> Ensure that your identity provider is returning the %username attribute and that it is set to %value for your account or you may not be able to log in.', $this->getExpectedUserAttributesReplacements()),
      '#default_value' => $this->ssoConfiguration->isRequired(),
      '#states' => [
        'visible' => [
          ':input[name="enable_saml_login"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Check if the directories are ready for the file uploads.
    $directory_to_prepare = 'private://saml_config';
    $status = $this->fileSystem->prepareDirectory($directory_to_prepare);
    if (!$status) {
      $created = $this->fileSystem->mkdir($directory_to_prepare);
      if (!$created) {
        $directory = ['@directory' => $directory_to_prepare];
        $form_state->setErrorByName('upload_metadata', $this->t('Cannot create directory @directory. Please contact administrator.', $directory));
      }
    }

    // Validate the uploaded file for errors.
    $file = $form_state->getValue('upload_metadata');
    if (!empty($file)) {
      $fid = reset($file);
      $uploaded_file = $this->entityTypeManager->getStorage('file')->load($fid);
      if ($uploaded_file instanceof FileInterface) {
        $file_uri = $uploaded_file->getFileUri();
        $xmldata = trim(file_get_contents($file_uri));
        // A SAML message should not contain a doctype-declaration.
        if (strpos($xmldata, '<!DOCTYPE') !== FALSE) {
          $uploaded_file->delete();
          $form_state->setErrorByName('upload_metadata', $this->t('XML contained a doctype declaration.'));
        }
        else {
          $is_valid = XML::isValid($xmldata, 'saml-schema-metadata-2.0.xsd');
          if (is_string($is_valid)) {
            // Log the error if the schema fails while XML validation.
            \Drupal::logger('externalauth_additions')->warning($is_valid);
            $uploaded_file->delete();
            $form_state->setErrorByName('upload_metadata', $this->t('The XML schema for the uploaded metadata file is not valid.'));
          }
        }
        $form_state->set('xml_data', $xmldata);
        $form_state->set('uploaded_file', $uploaded_file);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uploaded_file = $form_state->get('uploaded_file');
    $existing_fid = $this->state->get('externalauth_additions_uploaded_xml_file');
    // Delete the existing file if present.
    if ($existing_fid && $existing_fid != $uploaded_file->id()) {
      $existing_file = $this->entityTypeManager->getStorage('file')->load($existing_fid);
      if ($existing_file instanceof FileInterface) {
        $existing_file->delete();
      }
    }

    // Only save if the file is temporary. This happens with every new file.
    if ($uploaded_file->isTemporary()) {
      // Save file permanently.
      $uploaded_file->setPermanent();
      $uploaded_file->save();
      $this->state->set('externalauth_additions_uploaded_xml_file', $uploaded_file->id());
    }

    $enable_saml_login = $form_state->getValue('enable_saml_login');
    $force_saml_login = $enable_saml_login && $form_state->getValue('force_saml_login');

    // Set phpsaml module configuration.
    $phpsaml_config = $this->configFactory()->getEditable('simplesamlphp_auth.settings');
    $activate = $enable_saml_login ? TRUE : FALSE;
    $phpsaml_config->set('activate', $activate);
    $phpsaml_config->save();

    $this->ssoConfiguration->setRequired($force_saml_login);

    if ($force_saml_login) {
      $this->showWarningAboutConfiguration();
    }

    $this->messenger()->addStatus($this->t('Configuration saved.'));
  }

  /**
   * Retrieves the configuration for the SimpleSAMLphp Auth module.
   */
  protected function ssoConfig() {
    return $this->config('simplesamlphp_auth.settings');
  }

  /**
   * Invoked when the user saves configuration that requires the use of SSO.
   *
   * To help avoid the user locking themselves out of the system, this warning
   * reminds the user to verify that they have SSO configured correctly.
   */
  private function showWarningAboutConfiguration() {
    $message = $this->t('<strong>The use of single sign-on is now required.</strong><br>Before you log out, ensure that your identity provider is returning the %username attribute and that it is set to %value for your account.', $this->getExpectedUserAttributesReplacements());
    \Drupal::messenger()->addWarning($message);
  }

  /**
   * Retrieve replacements for populating a message about SSO configuration.
   *
   * %username - the SSO attribute to use as the user's username.
   * %value - the current user's current username.
   *
   * @return array
   *   An associative array of replacements for %username and %value.
   */
  private function getExpectedUserAttributesReplacements() {
    return [
      '%username' => $this->ssoConfig()->get('user_name'),
      '%value' => $this->currentUser()->getAccountName(),
    ];
  }

  /**
   * Returns the render-able table with the SAML attributes.
   *
   * @return array
   *   Render array for the table.
   */
  private function attributeTable() {
    $header = [
      $this->t('Attribute'),
      $this->t('Value'),
    ];
    $data = [
      [
        'NameIDPolicy',
        $this->t('EMAIL'),
      ],
      [
        'mail',
        $this->t("The user's email address"),
      ],
      [
        'givenName',
        $this->t("The user's first name"),
      ],
      [
        'sn',
        $this->t("The user's last name"),
      ],
    ];
    return [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $data,
    ];
  }

}
