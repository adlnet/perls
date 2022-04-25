<?php

namespace Drupal\prompts\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\prompts\Prompt;
use Drupal\webform\WebformTokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides prompt block.
 *
 * @Block(
 *   id = "prompt_dashboard_block",
 *   admin_label = @Translation("Dashboard prompt block"),
 *   category = @Translation("Prompt"),
 * )
 */
class DashboardItemPromptBlock extends PromptBlockBase {

  /**
   * Webform token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * PromptTileBlock constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\prompts\Prompt $prompt_service
   *   The prompt service.
   * @param \Drupal\webform\WebformTokenManagerInterface $token_manager
   *   Webform token manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Prompt $prompt_service,
    WebformTokenManagerInterface $token_manager,
    EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $prompt_service);
    $this->tokenManager = $token_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('prompts.prompt'),
      $container->get('webform.token_manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'block_text' => $this->t('Take a moment to improve your recommendations'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $form = parent::blockForm($form, $form_state);

    $form['block_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Block text'),
      '#default_value' => $config['block_text'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['block_text'] = $values['block_text'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $forms = $this->prompt->loadPrompts();

    $build = [];
    if (!empty($forms)) {
      $build = [
        '#theme' => 'simple_banner',
        '#modal_link' => Url::fromRoute('prompts.new_dashboard_prompts'),
        '#title' => $config['block_text'],
        '#icon' => [
          '#theme' => 'image',
          '#attributes' => [
            'width' => '50px',
            'height' => '50px',
          ],
          '#uri' => self::getPromptIconPath(),
        ],
        '#bg_color' => [
          '#type' => 'value',
          '#markup' => '#fe8917',
        ],
        '#attached' => [
          'library' => [
            'core/drupal.dialog.ajax',
            'prompts/prompts.ui',
          ],
        ],
      ];
    }
    return $build;
  }

  /**
   * Gives back the full path of prompt block icon.
   *
   * @return string
   *   The absolute url of the icon.
   */
  public static function getPromptIconPath() {
    global $base_url;

    return sprintf('%s/%s/%s', $base_url, drupal_get_path('module', 'prompts'), '/img/icons/PromptIcon.svg');
  }

  /**
   * Gives back the prompt service.
   *
   * @return \Drupal\prompts\Prompt
   *   The prompt service.
   */
  public function getPromptService(): Prompt {
    return $this->prompt;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(
      parent::getCacheTags(),
      $this->entityTypeManager->getDefinition('webform_submission')
        ->getListCacheTags()
    );
  }

}
