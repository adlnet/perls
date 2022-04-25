<?php

namespace Drupal\entity_packager\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\State;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\entity_packager\EntityPackager;

/**
 * Form to configure the node packager module.
 */
class EntityPackagerAdminForm extends ConfigFormBase {

  /**
   * Drupal file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Drupal state api.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * The entity view saver service.
   *
   * @var \Drupal\entity_packager\EntityPackager
   */
  private $entityPackager;

  /**
   * OfflinePageAdminForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\State\State $state
   *   Drupal state api.
   * @param \Drupal\entity_packager\EntityPackager $entity_creator
   *   The entity view saver service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    FileSystemInterface $file_system,
    State $state,
    EntityPackager $entity_creator) {
    parent::__construct($config_factory);
    $this->fileSystem = $file_system;
    $this->state = $state;
    $this->entityPackager = $entity_creator;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['entity_packager.page_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_packager_page_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('file_system'),
      $container->get('state'),
      $container->get('entity_packager.entity_packager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('entity_packager.page_settings');
    $state = \Drupal::state();

    $form['content_generate'] = [
      '#type' => 'details',
      '#title' => $this->t('Packager settings'),
      '#open' => TRUE,
    ];

    $form['content_generate']['directory_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Package storage directory'),
      '#description' => $this->t('The folder which contains the entity packages. This should be uri format, like public://folder'),
      '#default_value' => $config->get('save_directory'),
      '#required' => TRUE,
    ];

    $form['content_generate']['wget_user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Wget user'),
      '#description' => $this->t('User id. The wget command will use this user to retrieves the pages.'),
      '#default_value' => $state->get('wget_user'),
      '#required' => TRUE,
    ];

    $form['content_generate']['user_agent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Agent'),
      '#description' => $this->t('The User Agent to send in the request header when generating package.'),
      '#default_value' => $config->get('user_agent'),
      '#required' => FALSE,
    ];

    $form['packages'] = [
      '#type' => 'details',
      '#title' => $this->t('Packages'),
      '#open' => TRUE,
    ];

    $form['packages']['content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types'),
      '#description' => $this->t("Please select which content you want be packaged. If you don't select any option, it will generate package from all content."),
      '#options' => node_type_get_names(),
      '#default_value' => $config->get('content_types'),
    ];

    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $form_values = $form_state->getValues();
    if (!$this->fileSystem->realpath($form_values['directory_path'])) {
      $form_state->setErrorByName('directory_path', $this->t('Not a valid path.'));
    }

    if (!is_int((int) $form_values['wget_user'])) {
      $form_state->setErrorByName('wget_user', $this->t('This is not a number.'));
    }
    elseif (User::load($form_values['wget_user']) === NULL) {
      $form_state->setErrorByName('wget_user', $this->t('This is not an existing user.'));
    }
    /** @var \Drupal\user\Entity\User $user */
    elseif (!User::load($form_values['wget_user'])->hasRole('packager')) {
      $form_state->setErrorByName('wget_user', $this->t('This user does not have the necessary packager permission.'));
    }

    // The user agent is being injected into wget.
    // Limiting to alphanumeric for now.
    if ($error_message = $this->entityPackager->isUserAgentUnsafe($form_values['user_agent'])) {
      $form_state->setErrorByName('user_agent', $error_message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('entity_packager.page_settings')
      ->set('save_directory', $form_state->getValue('directory_path'))
      ->set('user_agent', $form_state->getValue('user_agent'))
      ->set('content_types', array_filter($form_state->getValue('content_types')))
      ->save();

    $this->state->set('wget_user', $form_state->getValue('wget_user'));

    $is_writable = is_writable($this->fileSystem->realpath($form_state->getValue('directory_path')));
    $is_directory = is_dir($this->fileSystem->realpath($form_state->getValue('directory_path')));
    if (!$is_writable || !$is_directory) {
      $this->fileSystem->prepareDirectory($form_state->getValue('directory_path'), FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    }
    parent::submitForm($form, $form_state);
  }

}
