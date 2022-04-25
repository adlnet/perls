<?php

namespace Drupal\recommender\Plugin\RecommendationScoreCombine;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\recommender\Entity\RecommendationCandidate;
use Drupal\recommender\RecommendationEnginePluginInterface;
use Drupal\recommender\RecommendationScoreCombinePluginBase;
use Drupal\recommender\RecommendationServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Weighted Score Combine plugin.
 *
 * @RecommendationScoreCombinePlugin(
 *   id = "weighted_score_combine",
 *   label = @Translation("Weighted Score"),
 *   description = @Translation("This plugin combines recommendation engine scores using a weighted addition."),
 * )
 */
class WeightedScorePlugin extends RecommendationScoreCombinePluginBase {

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
      $container->get('recommender.recommendation_service')
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
    ConfigFactoryInterface $config_factory,
    Token $token,
    ModuleHandlerInterface $module_handler,
    LanguageManagerInterface $language_manager,
    RecommendationServiceInterface $recommendation_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory, $token, $module_handler, $language_manager);
    $this->recommendationService = $recommendation_service;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $config = $this->getConfig();
    $plugins = $this->recommendationService->getRecommendationEnginePlugins(RecommendationEnginePluginInterface::STAGE_SCORE_CANDIDATES);

    foreach ($plugins as $id => $plugin) {
      $form[$id] = [
        '#type' => 'number',
        '#step' => 0.01,
        '#title' => $this->t('Weight of %title', ['%title' => $plugin->label()]),
        '#default_value' => ($config->get($id)) ?: 1,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getScoresByPluginId(RecommendationCandidate $candidate): array {
    return array_reduce($candidate->get('scores')->referencedEntities(), function ($result, $score) {
      $plugin = $score->get('plugin_id')->value;
      $weight = $this->getConfig()->get($plugin) ?: 1;

      $result[$plugin] = $score->get('score')->value * $weight;
      return $result;
    }, []);
  }

}
