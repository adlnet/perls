<?php

namespace Drupal\recommender\Plugin\Recommendations;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\recommender\Entity\RecommendationCandidate;
use Drupal\recommender\Entity\RecommendationPluginScore;
use Drupal\recommender\RecommendationEngineException;

;
use Drupal\recommender\RecommendationEnginePluginBase;
use Drupal\recommender\RecommendationServiceInterface;
use Drupal\tools\ViewCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Pad Results Recommendation engine plugin.
 *
 * @RecommendationEnginePlugin(
 *   id = "pad_results_recommendation_plugin",
 *   label = @Translation("Pad Results Recommendation Plugin"),
 *   description = @Translation("Adds random recommendations if not enough candidates have been created."),
 *   stages = {
 *     "alter_candidates" = 0,
 *     "score_candidates" = 0,
 *   }
 * )
 */
class PadResultsRecommendationEnginePlugin extends RecommendationEnginePluginBase {
  /**
  * Default recommendation reason.
  */
  const DEFAULT_RECOMMENDATION_REASON = 'something you might want to explore';

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
    EntityTypeManagerInterface $entityTypeManager,
    RecommendationServiceInterface $recommendation_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory, $language_manager, $entityTypeManager);
    $this->recommendationService = $recommendation_service;
  }

  /**
   * {@inheritdoc}
   */
  public function alterCandidates($candidates, AccountInterface $user) {
    if (count($candidates) > $this->getNumberOfCandidates()) {
      return $candidates;
    }
    $language = $user->getPreferredLangcode();
    $langcodes = [
      'zxx' => 'zxx',
      'und' => 'und',
      $language => $language,
    ];
    // Get $count new nodes and return them.
    $collection = new ViewCollection($this->getNumberOfCandidates());
    $collection->addViewById('pad_recommendations', [], 'embed_1', $langcodes);
    $data = $collection->groupedResults();
    if (empty($data)) {
      return $candidates;
    }

    if (!empty($data[0]['content'])) {
      foreach ($data[0]['content'] as $node) {
        $candidate_entity = $this->recommendationService->getCandidateEntity($user, $node);
        // Remove old score references from candidates since they get reused.
        $candidate_entity->scores = [];
        $candidate_entity->save();
        $candidates[$node->id()] = $candidate_entity;
        $this->updateOrCreateScoreEntity($user, $node->id(), $this->getScore(), RecommendationPluginScore::STATUS_PROCESSING);
      }
    }
    return $candidates;
  }

  /**
   * {@inheritdoc}
   */
  public function scoreCandidates(array $candidates, AccountInterface $user) {
    foreach ($candidates as $nid => $candidate) {
      if (!($candidate instanceof RecommendationCandidate)) {
        $type = $candidate->gettype();
        throw new RecommendationEngineException("Recommendation Engine - Score Candidates - '$type' - Only Recommendation Candidate objects can be sent to plugin to be scored");
      }
      $node = $candidate->nid->entity;
      if ($node) {
        $score = $this->getScoreEntity($user->id(), $node->id());
        if ($score) {
          $candidate->scores[] = $score;
          $candidate->save();
        }

      }
      else {
        throw new RecommendationEngineException("Recommendation Engine - Score Candidates - Node not found");
      }
    }
  }

  /**
   * Calculate Score.
   *
   * Since this is the random recommendation engine it simply
   * returns a random number.
   */
  protected function getScore() {
    // Will always between 0.1 and 0.25.
    return mt_rand(100, 250) / 1000;
  }

}
