<?php

namespace Drupal\recommender\Plugin\Recommendations;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\recommender\RecommendationEnginePluginBase;

;
use Drupal\recommender\RecommendationServiceInterface;
use Drupal\tools\ViewCollection;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Revision Recommendation engine plugin.
 *
 * @RecommendationEnginePlugin(
 *   id = "revision_recommendation_plugin",
 *   label = @Translation("Revision Recommendation Plugin"),
 *   description = @Translation("Adds revision cards to the recommendation view."),
 *   stages = {
 *     "rerank_candidates" = 100,
 *   }
 * )
 */
class RevisionRecommendationEnginePlugin extends RecommendationEnginePluginBase {
  /**
   * Default recommendation reason.
   */
  const DEFAULT_RECOMMENDATION_REASON = 'related to your recent activity';

  /**
   * The recommendation service.
   *
   * @var Druapl\recommender\RecommendationServiceInterface
   */
  protected $recommendationService;

  /**
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('User Interests Recommendation Engine'),
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
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
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    RecommendationServiceInterface $recommendation_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory, $language_manager, $entity_type_manager);
    $this->recommendationService = $recommendation_service;
  }

  /**
   * {@inheritdoc}
   */
  public function rerankCandidates($candidates, AccountInterface $user) {
    $user = User::load($user->id());

    $collection = new ViewCollection($this->getNumberOfCandidates());
    $collection->addViewById('review_material', [], 'embed_1');
    $data = $collection->groupedResults();
    if (empty($data)) {
      return $candidates;
    }
    $max = 1.0;
    $min = 100000.0;
    foreach ($candidates as $candidate) {
      if ($candidate->getCombinedScore() > $max) {
        $max = $candidate->getCombinedScore();
      }
      if ($candidate->getCombinedScore() < $min) {
        $min = $candidate->getCombinedScore();
      }
    }
    foreach ($data[0]['content'] as $node) {
      $candidate_entity = $this->recommendationService->getCandidateEntity($user, $node);
      // Remove old score references from candidates since they get reused.
      $candidate_entity->scores = [];
      $candidate_entity->setCombinedScore($this->getScore($max, $min));
      $candidate_entity->setReason($this->getRecommendationReason());
      $candidate_entity->save();
      $candidates[$node->id()] = $candidate_entity;
    }

    return $candidates;
  }

  /**
   * Calculate Score.
   *
   * We randomly spread these card across the recommendations.
   */
  protected function getScore($max, $min) {
    return mt_rand($min * 1000, $max * 1000) / 1000;
  }

}
