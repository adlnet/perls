<?php

namespace Drupal\recommender;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBaseTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Utility\Token;
use Drupal\recommender\Entity\RecommendationCandidate;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an base class for Recommendation Engine plugins.
 *
 * Plugins extending this class need to define a plugin definition array
 * through annotation. These definition arrays may be altered through
 * hook_recommendation_score_combine_info_alter(). The definition includes the
 * following keys:
 * - id: The unique, system-wide identifier of the recommendation class.
 * - label: The human-readable name of the recommendation scorer class
 *   , translated.
 * - description: A human-readable description for the recommendation
 *   scorer class translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @RecommendationScoreCombinePlugin(
 *   id = "my_recommendation_scorer",
 *   label = @Translation("My Recommendation Scorer."),
 *   description = @Translation("A base scorer that does nothing.")
 * )
 * @endcode
 *
 * @see \Drupal\recommender\Annotation\RecommendationEngine
 * @see \Drupal\recommender\RecommendationEnginePluginManager
 * @see \Drupal\recommender\RecommendationEnginePluginInterface
 * @see plugin_api
 */
abstract class RecommendationScoreCombinePluginBase extends PluginBase implements RecommendationScoreCombinePluginInterface, ContainerFactoryPluginInterface {
  use ConfigFormBaseTrait;
  use TranslatedConfigTrait;
  /**
   * The logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The config Factory Interface.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The token replacement service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('Recommendation Score Combine'),
      $container->get('config.factory'),
      $container->get('token'),
      $container->get('module_handler'),
      $container->get('language_manager'),
    );
  }

  /**
   * Constructor for Recommendation Engine.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerInterface $logger_factory,
    ConfigFactory $config_factory,
    Token $token,
    ModuleHandlerInterface $module_handler,
    LanguageManagerInterface $languageManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger_factory;
    $this->configFactory = $config_factory;
    $this->token = $token;
    $this->moduleHandler = $module_handler;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfig();
    $form['recommendation_reason_template'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Recommendation Reason'),
      '#description' => $this->t('These fields support tokens; use <code>[recommendation_reason:primary]</code> and <code>[recommendation_reason:secondary]</code> for the primary and secondary reasons a candidate applies to the user.'),
    ];

    $form['recommendation_reason_template']['single'] = [
      '#type' => 'textfield',
      '#title' => $this->t('When there is a single reason'),
      '#default_value' => $config->get('recommendation_reason_template.single'),
    ];

    $form['recommendation_reason_template']['multiple'] = [
      '#type' => 'textfield',
      '#title' => $this->t('When there are multiple reasons'),
      '#default_value' => $config->get('recommendation_reason_template.multiple'),
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      $form['recommendation_reason_template']['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['recommendation_reason'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $config = $this->getConfig();
    $values = $form_state->getValues();
    foreach ($values as $id => $value) {
      $config->set($id, $value);
    }
    $config->save();
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getScore(RecommendationCandidate $candidate) {
    return array_sum($this->getScoresByPluginId($candidate));
  }

  /**
   * {@inheritdoc}
   */
  public function getReason(RecommendationCandidate $candidate, $langcode = NULL) {
    // Gets the top two scoring plugins.
    $scores = $this->getScoresByPluginId($candidate);
    arsort($scores);
    $top_plugins = array_keys(array_slice($scores, 0, 2, TRUE));

    // Get the recommendation reason for the top two scoring plugins.
    $all_reasons = $this->getReasonsByPluginId($candidate);
    $reasons = array_map(function ($plugin_id) use ($all_reasons) {
      return $all_reasons[$plugin_id];
    }, $top_plugins);

    if (empty($reasons)) {
      return '';
    }

    $template_id = count($reasons) === 1 ? 'recommendation_reason_template.single' : 'recommendation_reason_template.multiple';
    $template = $this->getTemplate($template_id, $langcode);
    $data = [
      'recommendation_reasons' => $reasons,
    ];

    // The recommendation reason should be plain text,
    // but token replacement will try and escape some values;
    // we reverse that effect here.
    $options = ($langcode) ? ['langcode' => $langcode] : [];
    $reason = $this->token->replace($template, $data, $options);
    return Html::decodeEntities($reason);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['recommender.plugin.' . $this->pluginId . '.settings'];
  }

  /**
   * Get this plugins config settings.
   */
  protected function getConfig() {
    return $this->config('recommender.plugin.' . $this->pluginId . '.settings');
  }

  /**
   * Get a translated config template.
   */
  protected function getTemplate($template_id, $langCode = NULL) {
    return $this->translatedConfigOrDefault('recommender.plugin.' . $this->pluginId . '.settings', $template_id, $langCode);
  }

  /**
   * Retrieves the scores given by each plugin to the candidate.
   *
   * @param \Drupal\recommender\Entity\RecommendationCandidate $candidate
   *   The recommendation candidate.
   *
   * @return array
   *   An associative array of scores for the candidate keyed by plugin id.
   */
  abstract protected function getScoresByPluginId(RecommendationCandidate $candidate): array;

  /**
   * Retrieves the reasons why this candidate was recommended.
   *
   * @param \Drupal\recommender\Entity\RecommendationCandidate $candidate
   *   The recommendation candidate.
   *
   * @return array
   *   An associative array of reasons for the candidate keyed by plugin id.
   */
  protected function getReasonsByPluginId(RecommendationCandidate $candidate): array {
    return array_reduce($candidate->get('scores')->referencedEntities(), function ($result, $score) {
      $result[$score->get('plugin_id')->value] = $score->get('recommendation_reason')->value;
      return $result;
    }, []);
  }

}
