<?php

namespace Drupal\recommender\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\recommender\RecommendationEnginePluginInterface;
use Drupal\recommender\RecommendationServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure xAPI content settings for this site.
 */
class RecommendationAdminSettingsForm extends ConfigFormBase {

  /**
   * The recommendation service.
   *
   * @var \Drupal\recommender\RecommendationServiceInterface
   */
  protected $recommendationService;

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
   * @param \Drupal\recommender\RecommendationServiceInterface $recommendation_service
   *   The recommendation service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    RecommendationServiceInterface $recommendation_service,
    LoggerInterface $logger
  ) {
    parent::__construct($config_factory);
    $this->recommendationService = $recommendation_service;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('recommender.recommendation_service'),
      $container->get('logger.channel.recommendation_engine')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recommender_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['recommender.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#attached']['library'][] = 'recommender/recommender.recommendation_serviceation_plugins';
    $config = $this->config('recommender.settings');

    // Retrieve lists of all processors, and the stages and weights they have.
    if (!$form_state->has('recommendation_plugins')) {
      $all_recommendation_plugins = $this->recommendationService->getRecommendationEnginePlugins();
      $sort_recommendation_plugins = function (RecommendationEnginePluginInterface $a, RecommendationEnginePluginInterface $b) {
        return strnatcasecmp($a->label(), $b->label());
      };
      uasort($all_recommendation_plugins, $sort_recommendation_plugins);
      $form_state->set('recommendation_plugins', $all_recommendation_plugins);
    }
    else {
      $all_recommendation_plugins = $form_state->get('recommendation_plugins');
    }

    // Get a list of all recommendation computation stages.
    $stages = $this->recommendationService->getRecommendationStages();
    // Get a list of plugins by supported stage.
    $plugins_by_stage = [];
    foreach ($stages as $stage => $definition) {
      $plugins_by_stage[$stage] = $this->recommendationService->getRecommendationEnginePlugins($stage);
    }

    // Since multiple plugins are setting config here we need tree
    // to avoid naming conflicts.
    $form['#tree'] = TRUE;
    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General Recommendation Engine settings'),
      '#open' => TRUE,
    ];

    $form['debug'] = [
      '#type' => 'details',
      '#title' => $this->t('Debug Settings'),
      '#open' => FALSE,
    ];

    $form['general']['cron_recommendations'] = [
      '#type' => 'select',
      '#title' => $this->t('Calculate Recommendations on Cron'),
      '#description' => $this->t('When enabled new recommendations will only generated on cron runs (recommended). If disabled the system will attempt to recalculate recommendations in real time.'),
      '#options' => [
        0 => $this->t('False'),
        1 => $this->t('True'),
      ],
      '#default_value' => $config->get('cron_recommendations') != NULL ? $config->get('cron_recommendations') : 0,
    ];

    $form['general']['build_recommendations_with_ajax'] = [
      '#type' => 'select',
      '#title' => $this->t('Build Recommendations with Ajax'),
      '#description' => $this->t('If enabled the recommendation engine will use ajax to fetch new recommendations (if needed) every time the user visits the dashboard.'),
      '#options' => [
        0 => $this->t('False'),
        1 => $this->t('True'),
      ],
      '#default_value' => $config->get('build_recommendations_with_ajax') != NULL ? $config->get('build_recommendations_with_ajax') : 0,
    ];

    $form['general']['recommendation_ajax_view'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ajax Recommendation view name.'),
      '#description' => $this->t('The id of the view that should be refreshed with ajax when new recommendations are available.'),
      '#default_value' => $config->get('recommendation_ajax_view') ?: 'vault_recommendations',
      '#states' => [
        'visible' => [
          'select[name="general[build_recommendations_with_ajax]"]' => ['value' => 1],
        ],
        'invisible' => [
          'select[name="general[build_recommendations_with_ajax]"]' => ['value' => 0],
        ],

      ],
    ];

    $form['general']['recommendation_ajax_view_display_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ajax Recommendation view display id.'),
      '#description' => $this->t('The id of the view display that should be refreshed with ajax when new recommendations are available.'),
      '#default_value' => $config->get('recommendation_ajax_view_display_id') ?: 'block_1',
      '#states' => [
        'visible' => [
          'select[name="general[build_recommendations_with_ajax]"]' => ['value' => 1],
        ],
        'invisible' => [
          'select[name="general[build_recommendations_with_ajax]"]' => ['value' => 0],
        ],

      ],
    ];

    $form['general']['build_recommendations_on_login'] = [
      '#type' => 'select',
      '#title' => $this->t('Build Recommendations on first log in'),
      '#description' => $this->t('If enabled the recommendation engine build recommendations when the user first logs in'),
      '#options' => [
        0 => $this->t('False'),
        1 => $this->t('True'),
      ],
      '#default_value' => $config->get('build_recommendations_on_login') != NULL ? $config->get('build_recommendations_on_login') : 0,
    ];

    $form['general']['build_recommendations_on_registration'] = [
      '#type' => 'select',
      '#title' => $this->t('Build Recommendations on registration'),
      '#description' => $this->t('If enabled the recommendation engine build recommendations when the user is first created'),
      '#options' => [
        0 => $this->t('False'),
        1 => $this->t('True'),
      ],
      '#default_value' => $config->get('build_recommendations_on_registration') != NULL ? $config->get('build_recommendations_on_registration') : 1,
    ];

    $form['general']['build_recommendations_on_user_update'] = [
      '#type' => 'select',
      '#title' => $this->t('Build Recommendations on user update'),
      '#description' => $this->t('If enabled the recommendation engine build recommendations when the user is updated'),
      '#options' => [
        0 => $this->t('False'),
        1 => $this->t('True'),
      ],
      '#default_value' => $config->get('build_recommendations_on_user_update') != NULL ? $config->get('build_recommendations_on_user_update') : 1,
    ];

    $form['general']['recommendation_flag_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recommendation Flag ID'),
      '#description' => $this->t('The id of the flag object to use to mark content as recommended.'),
      '#default_value' => $config->get('recommendation_flag_id') ?: 'recommendation',
    ];

    $form['general']['store_history'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Store Recommendation History'),
      '#description' => $this->t('Enter a relative date for how long you want to store recommendation history, e.g 1 days, 2 weeks, 3 months, never, forever'),
      '#default_value' => $config->get('store_history') ?: 'forever',
    ];

    $form['general']['stale_recommendations'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recommendation Freshness'),
      '#description' => $this->t('Enter a relative date for how long recommendations remain fresh, e.g 1 days, 2 weeks, 3 months. After this time new recommendations will be generated for the user.'),
      '#default_value' => $config->get('stale_recommendations') ?: '4 weeks',
    ];

    $form['general']['recommendation_timeout'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recommendation Generation frequency'),
      '#description' => $this->t('Enter a relative date for the minimum time before recommendations are recalculated, e.g 30 minutes, 2 hours, 3 days. After this time new recommendations will be generated for the user.'),
      '#default_value' => $config->get('recommendation_timeout') ?: '0 seconds',
    ];

    $form['debug']['enable_debug'] = [
      '#type' => 'select',
      '#title' => $this->t('Enable Debug'),
      '#description' => $this->t('If enabled extra information is logged during recommendation engine runs.'),
      '#options' => [
        0 => $this->t('False'),
        1 => $this->t('True'),
      ],
      '#default_value' => $config->get('enable_debug') != NULL ? $config->get('enable_debug') : 0,
    ];

    $form['status'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Recommendation Plugins Enabled'),
      '#attributes' => [
        'class' => [
          'recommendation-plugin-status-wrapper',
        ],
      ],
    ];

    foreach ($all_recommendation_plugins as $plugin_id => $plugin) {
      $clean_css_id = Html::cleanCssIdentifier($plugin_id);
      $form['status'][$plugin_id] = [
        '#type' => 'checkbox',
        '#title' => $plugin->label(),
        '#default_value' => $config->get($plugin_id . '_enabled'),
        '#description' => $plugin->getDescription(),
        '#attributes' => [
          'class' => [
            'recommendation-engine-plugin-status-' . $clean_css_id,
          ],
          'data-id' => $clean_css_id,
        ],
      ];
    }

    $form['weights'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Recommendation Plugin order'),
    ];
    // Order enabled processors per stage.
    foreach ($stages as $stage => $description) {
      $form['weights'][$stage] = [
        '#type' => 'fieldset',
        '#title' => $description['label'],
        '#attributes' => [
          'class' => [
            'recommender-recommendation-stage-wrapper',
            'recommender-recommendation-stage-wrapper-' . Html::cleanCssIdentifier($stage),
          ],
        ],
      ];
      $form['weights'][$stage]['order'] = [
        '#type' => 'table',
      ];
      $form['weights'][$stage]['order']['#tabledrag'][] = [
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'recommender-recommendation-plugin-weight-' . Html::cleanCssIdentifier($stage),
      ];
    }

    foreach ($plugins_by_stage as $stage => $plugins) {
      // Sort the processors by weight for this stage.
      $plugin_weights = [];
      foreach ($plugins as $plugin_id => $plugin) {
        $plugin_weights[$plugin_id] = $plugin->getWeight($stage);
      }
      asort($plugin_weights);

      foreach ($plugin_weights as $plugin_id => $weight) {
        $plugin = $plugins[$plugin_id];
        $form['weights'][$stage]['order'][$plugin_id]['#attributes']['class'][] = 'draggable';
        $form['weights'][$stage]['order'][$plugin_id]['#attributes']['class'][] = 'recommender-recommendation-plugin-weight--' . Html::cleanCssIdentifier($plugin_id);
        $form['weights'][$stage]['order'][$plugin_id]['#weight'] = $weight;
        $form['weights'][$stage]['order'][$plugin_id]['label']['#plain_text'] = $plugin->label();
        $form['weights'][$stage]['order'][$plugin_id]['weight'] = [
          '#type' => 'weight',
          '#title' => $this->t('Weight for processor %title', ['%title' => $plugin->label()]),
          '#title_display' => 'invisible',
          '#delta' => 50,
          '#default_value' => $weight,
          '#parents' => ['plugins', $plugin_id, 'weights', $stage],
          '#attributes' => [
            'class' => [
              'recommender-recommendation-plugin-weight-' . Html::cleanCssIdentifier($stage),
            ],
          ],
        ];
      }
    }
    // Add individual plugin forms.
    $form['plugin_settings'] = [
      '#title' => $this->t('Recommendation plugin settings'),
      '#type' => 'vertical_tabs',
    ];

    foreach ($all_recommendation_plugins as $plugin_id => $plugin) {
      if ($plugin instanceof PluginFormInterface) {
        $form['settings'][$plugin_id] = [
          '#type' => 'details',
          '#title' => $plugin->label(),
          '#group' => 'plugin_settings',
          '#parents' => ['plugins', $plugin_id, 'settings'],
          '#attributes' => [
            'class' => [
              'recommender-recommendation-plugin-settings-' . Html::cleanCssIdentifier($plugin_id),
            ],
          ],
        ];
        $plugin_form_state = SubformState::createForSubform($form['settings'][$plugin_id], $form, $form_state);
        $form['settings'][$plugin_id] += $plugin->buildConfigurationForm($form['settings'][$plugin_id], $plugin_form_state);
      }
      else {
        unset($form['settings'][$plugin_id]);
      }
    }
    $form['score_combine'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Score Combine Settings'),
    ];
    $score_combine_plugins = $this->recommendationService->getScoreCombinePlugins();
    $options = [];
    foreach ($score_combine_plugins as $id => $score_combine_plugin) {
      $options[$id] = $score_combine_plugin->label();
      if ($score_combine_plugin instanceof PluginFormInterface) {
        $form['score_combine'][$id] = [
          '#type' => 'details',
          '#title' => $score_combine_plugin->label(),
          '#parents' => ['score_combine', $id],
          '#attributes' => [
            'class' => [
              'recommender-recommendation-plugin-settings-' . Html::cleanCssIdentifier($id),
            ],
          ],
          '#states' => [
            'visible' => [
              ':input[name="score_combine[score_combine_plugin]"]' => [
                'value' => $id,
              ],
            ],
          ],
          '#weight' => 10,

        ];
        $plugin_form_state = SubformState::createForSubform($form['score_combine'][$id], $form, $form_state);
        $form['score_combine'][$id] += $score_combine_plugin->buildConfigurationForm($form['score_combine'][$id], $plugin_form_state);
      }
      else {
        unset($form['score_combine'][$id]);
      }

    }
    $form['score_combine']['score_combine_plugin'] = [
      '#type' => 'select',
      '#description' => $this->t('Choose how the scores from the various plugins will be combined into the final score.'),
      '#options' => $options,
      '#default_value' => $config->get('score_combine_plugin'),
      '#weight' => 0,
    ];

    // Reset index button.
    $form['actions']['clear'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset Data'),
      '#name' => 'clear',
    ];
    // Remove all recommendations.
    $form['actions']['reset_recommendations'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete all Recommendations'),
      '#name' => 'reset_recommendations',
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

    $values = $form_state->getValues();
    $plugins = $this->recommendationService->getRecommendationEnginePlugins();
    foreach (array_keys(array_filter($values['status'])) as $plugin_id) {
      $plugin = $plugins[$plugin_id];
      if ($plugin instanceof PluginFormInterface) {
        $plugin_form_state = SubformState::createForSubform($form['settings'][$plugin_id], $form, $form_state);
        $plugin->validateConfigurationForm($form['settings'][$plugin_id], $plugin_form_state);
      }
    }
    $plugins = $this->recommendationService->getScoreCombinePlugins();
    if (isset($values['score_combine']['score_combine_plugin']) && isset($plugins[$values['score_combine']['score_combine_plugin']])) {
      $plugin_id = $values['score_combine']['score_combine_plugin'];
      $plugin = $plugins[$values['score_combine']['score_combine_plugin']];
      if ($plugin instanceof PluginFormInterface) {
        $plugin_form_state = SubformState::createForSubform($form['score_combine'][$plugin_id], $form, $form_state);
        $plugin->validateConfigurationForm($form['score_combine'][$plugin_id], $plugin_form_state);
      }

    }
    // Check the relative time fields.
    $store_history = $form_state->getValue([
      'general', 'store_history',
    ]);
    if (!in_array($store_history, ['never', 'forever']) && strtotime('-' . $store_history) === FALSE) {
      $form_state->setErrorByName('general][store_history', $this->t('Store recommendation history must be a valid relative date e.g. 3 days'));
    }
    $stale_recommendations = $form_state->getValue([
      'general', 'stale_recommendations',
    ]);
    if (!strtotime('-' . $stale_recommendations)) {
      $form_state->setErrorByName('general][stale_recommendations', $this->t('Recommendation freshness must be a valid relative date e.g. 3 days'));
    }

    $recommendation_timeout = $form_state->getValue([
      'general', 'recommendation_timeout',
    ]);
    if (!strtotime('-' . $recommendation_timeout)) {
      $form_state->setErrorByName('general][recommendation_timeout', $this->t('Recommendation frequency must be a valid relative date e.g. 3 days'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('recommender.settings');
    // We have multiple submit buttons so deal with that here.
    switch ($form_state->getTriggeringElement()['#name']) {
      case 'clear':
        if ($this->recommendationService->resetUserRecommendations()) {
          $this->messenger()->addStatus($this->t('Resetting Recommendation engine.'));
        }
        else {
          $this->messenger()->addError($this->t('Failed to reset the recommendation engine'));
        }
        break;

      case 'queue_recommendations':
        // Set batch to calculated recommendations for all users.
        $form_state->setRedirect('recommender.batch_recommendation_form');
        break;

      case 'reset_recommendations':
        $form_state->setRedirect('recommender.batch_delete_recommendation_form');
        break;

      default:
        $values = $form_state->getValues();
        $recommendation_plugins = $this->recommendationService->getRecommendationEnginePlugins();
        // Set General settings first.
        $config
          ->set('cron_recommendations', $form_state->getValue([
            'general',
            'cron_recommendations',
          ]))
          ->set('build_recommendations_with_ajax', $form_state->getValue([
            'general',
            'build_recommendations_with_ajax',
          ]))
          ->set('recommendation_ajax_view', $form_state->getValue([
            'general', 'recommendation_ajax_view',
          ]))
          ->set('recommendation_ajax_view_display_id', $form_state->getValue([
            'general', 'recommendation_ajax_view_display_id',
          ]))
          ->set('build_recommendations_on_login', $form_state->getValue([
            'general',
            'build_recommendations_on_login',
          ]))
          ->set('build_recommendations_on_registration', $form_state->getValue([
            'general',
            'build_recommendations_on_registration',
          ]))
          ->set('build_recommendations_on_user_update', $form_state->getValue([
            'general',
            'build_recommendations_on_user_update',
          ]))
          ->set('recommendation_flag_id', $form_state->getValue([
            'general', 'recommendation_flag_id',
          ]))
          ->set('store_history', $form_state->getValue([
            'general', 'store_history',
          ]))
          ->set('stale_recommendations', $form_state->getValue([
            'general', 'stale_recommendations',
          ]))
          ->set('recommendation_timeout', $form_state->getValue([
            'general', 'recommendation_timeout',
          ]))
          ->set('enable_debug', $form_state->getValue([
            'debug', 'enable_debug',
          ]))
          ->set('score_combine_plugin', $form_state->getValue([
            'score_combine',
            'score_combine_plugin',
          ]))
          ->save();

        foreach ($recommendation_plugins as $plugin_id => $plugin) {
          $config
            ->set($plugin_id . '_enabled', $values['status'][$plugin_id])
            ->save();
          if (empty($values['status'][$plugin_id])) {
            // Delete Recommendations of disabled plugins.
            $this->recommendationService->removePluginScores($plugin_id);
            continue;
          }
          if ($plugin instanceof PluginFormInterface) {
            // Submit to the plugin handler for further processing.
            $plugin_form_state = SubformState::createForSubform($form['settings'][$plugin_id], $form, $form_state);
            $plugin->submitConfigurationForm($form['settings'][$plugin_id], $plugin_form_state);
          }
          if (!empty($values['plugins'][$plugin_id]['weights'])) {
            foreach ($values['plugins'][$plugin_id]['weights'] as $stage => $weight) {
              $plugin->setWeight($stage, (int) $weight);
            }
          }
        }
        $plugins = $this->recommendationService->getScoreCombinePlugins();
        if (isset($values['score_combine']['score_combine_plugin']) && isset($plugins[$values['score_combine']['score_combine_plugin']])) {
          $plugin_id = $values['score_combine']['score_combine_plugin'];
          $plugin = $plugins[$values['score_combine']['score_combine_plugin']];
          if ($plugin instanceof PluginFormInterface) {
            $plugin_form_state = SubformState::createForSubform($form['score_combine'][$plugin_id], $form, $form_state);
            $plugin->submitConfigurationForm($form['score_combine'][$plugin_id], $plugin_form_state);
          }
        }

        parent::submitForm($form, $form_state);
        break;
    }

  }

}
