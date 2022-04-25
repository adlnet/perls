<?php

namespace Drupal\perls_recommendation\Form;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\perls_recommendation\RecommendationEnginePluginManager;
use Drupal\perls_recommendation\RecommendService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure xAPI content settings for this site.
 */
class PerlsRecommendationAdminSettingsForm extends ConfigFormBase {

  /**
   * The recommendation engine plugin manager.
   *
   * @var \Drupal\perls_recommendation\RecommendationEnginePluginManager
   */
  protected $recommendationEngineManager;

  /**
   * The recommendation service.
   *
   * @var \Drupal\perls_recommendation\RecommendService
   */
  protected $recommendationService;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\perls_recommendation\RecommendationEnginePluginManager $recommendation_engine_manager
   *   The plugin manager service for recommendation engines.
   * @param \Drupal\perls_recommendation\RecommendService $recommendation_service
   *   The recommendation service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    RecommendationEnginePluginManager $recommendation_engine_manager,
    RecommendService $recommendation_service
  ) {
    parent::__construct($config_factory);
    $this->recommendationEngineManager = $recommendation_engine_manager;
    $this->recommendationService = $recommendation_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.recommendation_engine'),
      $container->get('perls_recommendation.recommend')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'perls_recommendation_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['perls_recommendation.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('perls_recommendation.settings');
    $plugin_definitions = $this->recommendationEngineManager->getDefinitions();

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General Recommendation Engine settings'),
      '#open' => TRUE,
    ];

    $form['general']['use_queue_graph'] = [
      '#type' => 'select',
      '#title' => 'Use Queue Api to build graph',
      '#options' => [
        0 => $this->t('False'),
        1 => $this->t('True'),
      ],
      '#default_value' => $config->get('use_queue_graph') ? $config->get('use_queue_graph') : 0,
    ];

    $form['general']['use_queue_recommend'] = [
      '#type' => 'select',
      '#title' => 'Use Queue Api for recommendations',
      '#options' => [
        0 => $this->t('False'),
        1 => $this->t('True'),
      ],
      '#default_value' => $config->get('use_queue_recommend') ? $config->get('use_queue_recommend') : 0,
    ];

    $form['general']['recommendation_flag_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recommendation Flag ID'),
      '#description' => $this->t('The id of the flag object to use to mark content as recommended.'),
      '#default_value' => $config->get('recommendation_flag_id') ?: 'recommendation',
    ];

    $form['general']['initial_recommendation_view_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Initial Content View id'),
      '#description' => $this->t('The id of the view used to populated initial recommendations.'),
      '#default_value' => $config->get('initial_recommendation_view_id') ?: 'trending_content',
    ];

    $form['general']['initial_recommendation_reason'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Initial Content Reasoning'),
      '#description' => $this->t('The reason for recommending this content.'),
      '#default_value' => $config->get('initial_recommendation_reason') ?: 'Good Content for new users.',
    ];

    $form['general']['initial_recommendation_weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Initial Content recommendation weight'),
      '#description' => $this->t('The max weight a intial recommendation can get. In reality it will be randomly distributed over a scale between this number and 0.'),
      '#default_value' => $config->get('initial_recommendation_weight') ?: 0.5,
      '#step' => 0.01,
    ];

    $form['general']['filler_recommendation_view_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filler Content View id'),
      '#description' => $this->t('The id of the view used to populated filler recommendations.'),
      '#default_value' => $config->get('filler_recommendation_view_id') ?: 'trending_content',
    ];

    $form['general']['filler_recommendation_reason'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filler Content Reasoning'),
      '#description' => $this->t('The reason for recommending this content.'),
      '#default_value' => $config->get('filler_recommendation_reason') ?: 'Popular Content.',
    ];

    $form['general']['filler_recommendation_weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Filler Content recommendation weight'),
      '#description' => $this->t('The max weight a Filler recommendation can get. In reality it will be randomly distributed over a scale between this number and 0.'),
      '#default_value' => $config->get('initial_recommendation_weight') ?: 0.4,
      '#step' => 0.01,
    ];

    foreach ($plugin_definitions as $id => $definition) {
      $form[$id] = [
        '#type'  => 'details',
        '#title' => $definition['label'],
        '#description' => $definition['description'],
        '#open'  => TRUE,
      ];
      $form[$id][$id . '_enabled'] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Enable'),
        '#default_value' => $config->get($id . '_enabled'),
      ];
      $form[$id][$id . '_number_recommendations'] = [
        '#type'          => 'number',
        '#title'         => $this->t('Number of recommendations'),
        '#default_value' => $config->get($id . '_number_recommendations'),
        '#size'          => 60,
        '#maxlength'     => 60,
        '#max'           => 100,
        '#min'           => 1,
        '#states'        => [
          'visible' => [
            ':input[name="' . $id . '_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];
      // Load a version of the recommendation engine plugin.
      try {
        /** @var \Drupal\perls_recommendation\RecommendationEnginePluginInterface $recommendation_engine */
        $recommendation_engine = $this->recommendationEngineManager
          ->createInstance($id);
      }
      catch (PluginException $e) {
        continue;
      }
      // Check to see if plugin wants to add to this form.
      // Attach the recommendation engine plugin configuration form.
      $plugin_form_state = SubformState::createForSubform($form[$id], $form, $form_state);
      $form[$id] = $recommendation_engine->buildConfigurationForm($form[$id], $plugin_form_state, $config);
    }

    $form = parent::buildForm($form, $form_state);
    // Since various plugins are creating forms we need
    // the form to be a tree to avoid naming conflicts.
    $form['#tree'] = TRUE;
    // Reset index button.
    $form['actions']['clear'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset Graph'),
      '#name' => 'clear',
    ];
    // Rebuild graph button.
    $form['actions']['rebuild'] = [
      '#type' => 'submit',
      '#value' => $this->t('Rebuild Graph'),
      '#name' => 'rebuild',
    ];
    // Queue all users for recommendations.
    $form['actions']['queue_recommendations'] = [
      '#type' => 'submit',
      '#value' => $this->t('Queue all Users for Recommendations'),
      '#name' => 'queue_recommendations',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $plugin_definitions = $this->recommendationEngineManager->getDefinitions();
    foreach ($plugin_definitions as $id => $definition) {
      // Load a version of the recommendation engine plugin.
      try {
        /** @var \Drupal\perls_recommendation\RecommendationEnginePluginInterface $recommendation_engine */
        $recommendation_engine = $this->recommendationEngineManager
          ->createInstance($id);
      }
      catch (PluginException $e) {
        continue;
      }
      // Check to see if plugin wants to add to this form.
      // Attach the recommendation engine plugin configuration form.
      $plugin_form_state = SubformState::createForSubform($form[$id], $form, $form_state);
      $recommendation_engine->validateConfigurationForm($form[$id], $plugin_form_state);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // We have multiple submit buttons so deal with that here.
    switch ($form_state->getTriggeringElement()['#name']) {
      case 'clear':
        if ($this->recommendationService->resetGraph()) {
          $this->messenger()->addStatus($this->t('Successfully reset recommendation graph'));
        }
        else {
          $this->messenger()->addError($this->t('Failed to reset the recommendation graph'));
        }
        break;

      case 'rebuild':
        $this->recommendationService->rebuildGraph();
        break;

      case 'queue_recommendations':
        $this->recommendationService->queueAllUserRecommendations();
        $this->messenger()->addStatus($this->t('All users have been queued for recommendations.'));
        break;

      default:
        $config = $this->config('perls_recommendation.settings');
        // @codingStandardsIgnoreStart
        $config
          ->set('use_queue_graph', $form_state->getValue(['general', 'use_queue_graph']))
          ->set('use_queue_recommend', $form_state->getValue(['general', 'use_queue_recommend']))
          ->set('recommendation_flag_id', $form_state->getValue(['general', 'recommendation_flag_id']))
          ->set('initial_recommendation_view_id', $form_state->getValue(['general', 'initial_recommendation_view_id']))
          ->set('initial_recommendation_weight', $form_state->getValue(['general', 'initial_recommendation_weight']))
          ->set('initial_recommendation_reason', $form_state->getValue(['general', 'initial_recommendation_reason']))
          ->set('filler_recommendation_view_id', $form_state->getValue(['general', 'filler_recommendation_view_id']))
          ->set('filler_recommendation_weight', $form_state->getValue(['general', 'filler_recommendation_weight']))
          ->set('filler_recommendation_reason', $form_state->getValue(['general', 'filler_recommendation_reason']))
          ->save();
        // @codingStandardsIgnoreEnd
        $plugin_definitions = $this->recommendationEngineManager->getDefinitions();
        foreach ($plugin_definitions as $id => $definition) {
          // @codingStandardsIgnoreStart
          $config
            ->set($id . '_enabled', $form_state->getValue([$id, $id . '_enabled']))
            ->set($id . '_number_recommendations', $form_state->getValue([$id, $id . '_number_recommendations']))
            ->save();
          // @codingStandardsIgnoreEnd
          // Delete recommendations of disabled plugins.
          if (!$form_state->getValue([$id, $id . '_enabled'])) {
            $this->recommendationService->removeCurrentflags($id);
          }
          // Load a version of the recommendation engine plugin.
          try {
            /** @var \Drupal\perls_recommendation\RecommendationEnginePluginInterface $recommendation_engine */
            $recommendation_engine = $this->recommendationEngineManager
              ->createInstance($id);
          }
          catch (PluginException $e) {
            continue;
          }
          // Check to see if plugin wants to add to this form.
          // Attach the recommendation engine plugin configuration form.
          $plugin_form_state = SubformState::createForSubform($form[$id], $form, $form_state);
          $recommendation_engine->submitConfigurationForm($form[$id], $plugin_form_state, $config);
        }

        parent::submitForm($form, $form_state);
        break;
    }

  }

}
