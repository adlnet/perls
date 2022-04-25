<?php

namespace Drupal\content_moderation_additions\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure content moderation settings for this site.
 */
class ModerationAdditionsAdminSettingsForm extends ConfigFormBase {

  /**
   * The logger to use.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LoggerInterface $logger
  ) {
    parent::__construct($config_factory);
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.channel.content_moderation_additions')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_moderation_additions_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['content_moderation_additions.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('content_moderation_additions.settings');

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General content moderation additions settings'),
      '#open' => TRUE,
    ];

    $form['general']['enable_reviewer'] = [
      '#type' => 'select',
      '#title' => $this->t('Enable reviewer field'),
      '#description' => $this->t('When enabled a reviewer must be set on all moderated entities.'),
      '#options' => [
        0 => $this->t('No'),
        1 => $this->t('Yes'),
      ],
      '#default_value' => $config->get('enable_reviewer') ? $config->get('enable_reviewer') : 0,
    ];

    $form['general']['enable_moderation_comments'] = [
      '#type' => 'select',
      '#title' => $this->t('Enable moderation comments'),
      '#description' => $this->t('If enabled admin/reviewers can leave moderation comments on the article. The moderation comment type must be present to use this setting.'),
      '#options' => [
        0 => $this->t('No'),
        1 => $this->t('Yes'),
      ],
      '#default_value' => $config->get('enable_moderation_comments') ? $config->get('enable_moderation_comments') : 0,
    ];

    $form['general']['allow_save_on_published'] = [
      '#type' => 'select',
      '#title' => $this->t('Show save button on published articles'),
      '#description' => $this->t('If enabled admin/reviewers can edit and make changes to published content without sending it back to draft/review.'),
      '#options' => [
        0 => $this->t('No'),
        1 => $this->t('Yes'),
      ],
      '#default_value' => $config->get('allow_save_on_published') ? $config->get('allow_save_on_published') : 0,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('content_moderation_additions.settings');
    $values = $form_state->getValues();
    $config
      ->set('enable_reviewer', $values['enable_reviewer'])
      ->set('enable_moderation_comments', $values['enable_moderation_comments'])
      ->set('allow_save_on_published', $values['allow_save_on_published'])
      ->save();
    parent::submitForm($form, $form_state);
  }

}
