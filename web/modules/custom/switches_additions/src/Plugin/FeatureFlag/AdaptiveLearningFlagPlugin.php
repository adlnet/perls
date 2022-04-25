<?php

namespace Drupal\switches_additions\Plugin\FeatureFlag;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\switches_additions\FeatureFlagPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\recommender\RecommendationService;
use Drupal\perls_adaptive_content\AdaptiveContentPluginManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a condition that always evaluates to false.
 *
 * @FeatureFlag(
 *   id = "adaptive_learning_feature",
 *   label = @Translation("Handles feature flag"),
 *   switchId = "adaptive_learning",
 *   supportedManagerInvokeMethods = {
 *     "entityView",
 *     "infoAlter",
 *     "testInfoAlter",
 *     "formAlter",
 *     "fieldWidgetFormAlter",
 *     "viewAccess",
 *     "getSwitchFeatureRoutes",
 *   },
 *   weight = "1",
 * )
 */
class AdaptiveLearningFlagPlugin extends FeatureFlagPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The recommendation service.
   *
   * @var \Drupal\recommender\RecommendationService
   */
  protected $recommendationService;

  /**
   * The adaptive content plugin manager.
   *
   * @var \Drupal\perls_adaptive_content\AdaptiveContentPluginManager
   */
  protected $adaptiveContentPluginManager;

  /**
   * Constructs a AdaptiveLearningFlagPlugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\recommender\RecommendationService $recommendation_service
   *   The recommendation service.
   * @param \Drupal\perls_adaptive_content\AdaptiveContentPluginManager $adaptive_content_plugin_manager
   *   The adaptive content plugin manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RecommendationService $recommendation_service = NULL, AdaptiveContentPluginManager $adaptive_content_plugin_manager = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->recommendationService = $recommendation_service;
    $this->adaptiveContentPluginManager = $adaptive_content_plugin_manager;
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('recommender.recommendation_service'),
      $container->get('plugin.manager.perls_adaptive_content')
    );
  }

  /**
   * List of routes which will be checked in viewAccess function.
   *
   * @return string[]
   *   List of drupal routes.
   */
  public function getSwitchFeatureRoutes() {
    return [
      'view.administrate_user_flags.administer_user_flags_recommended',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function featureWasDisabled() {
    $this->recommendationService->deleteUserRecommendations();
    $this->adaptiveContentPluginManager->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function featureWasEnabled() {
    $this->adaptiveContentPluginManager->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    if ($this->isSwitchDisabled() == FALSE) {
      return;
    }
    switch ($form_id) {
      case 'recommender_admin_settings':
        $form['general']['#description'] = $this->t('Note: Full feature disabled');
        break;

      case 'node_test_form':
      case 'node_test_edit_form':
        $form['field_adaptive_content']['#disabled'] = TRUE;
        $form['field_adaptive_content']['#type'] = 'hidden';
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewAccess(AccountInterface $account, RouteMatchInterface $route_match) {
    $route = $route_match->getRouteObject();
    $path = $route->getPath();
    if (!strpos($path, '/manage-flag/recommended')) {
      return AccessResult::neutral();
    }

    $allowed = AccessResult::allowed()->setCacheMaxAge(0);
    $forbidden = AccessResult::forbidden()->setCacheMaxAge(0);
    if (!$this->isSwitchDisabled()) {
      return $account->hasPermission('administer flaggings') ? $allowed : $forbidden;
    }

    return $forbidden;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldWidgetFormAlter(&$element, FormStateInterface &$form_state, $context) {
    if ($this->isSwitchDisabled() == FALSE || !isset($context['form']['#id'])) {
      return;
    }
    switch ($context['form']['#id']) {
      case 'edit-inline-entity-form':
        if ($context['form']['#bundle'] !== 'test' || $context['items']->getName() !== 'field_adaptive_content') {
          break;
        }
        $element['#disabled'] = TRUE;
        $element['#type'] = 'hidden';
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function infoAlter(&$re_info) {
    if ($this->isSwitchDisabled() == FALSE) {
      return;
    }
    $standard_plugins = function ($array_key) {
      $standard_recommendation_plugins = [
        'trending_content_recommendation_plugin',
        'new_content_recommendation_plugin',
        'pad_results_recommendation_plugin',
      ];
      return in_array($array_key, $standard_recommendation_plugins);
    };

    $re_info = array_filter($re_info, $standard_plugins, ARRAY_FILTER_USE_KEY) ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function testInfoAlter(&$re_info) {
    if ($this->isSwitchDisabled()) {
      $re_info = [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {
    if ($this->isSwitchDisabled() &&
      isset($build['recommendation_reason']['#attached']['library']['recommender/recommendation-tip-box'])) {
      unset($build['recommendation_reason']['#attached']['library']['recommender/recommendation-tip-box']);
    }
  }

}
