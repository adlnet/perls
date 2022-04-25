<?php

namespace Drupal\recommender\Plugin\Recommendations;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\recommender\Entity\RecommendationCandidate;
use Drupal\recommender\Entity\RecommendationPluginScore;

;
use Drupal\recommender\RecommendationEngineException;
use Drupal\recommender\RecommendationEnginePluginBase;
use Drupal\tools\ViewCollection;
use Drupal\statistics\NodeStatisticsDatabaseStorage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Trending Content Recommendation engine plugin.
 *
 * @RecommendationEnginePlugin(
 *   id = "trending_content_recommendation_plugin",
 *   label = @Translation("Trending Content Recommendation Plugin"),
 *   description = @Translation("Recommends popular content to users."),
 *   stages = {
 *     "generate_candidates" = 0,
 *     "score_candidates" = 0,
 *   }
 * )
 */
class TrendingContentRecommendationEnginePlugin extends RecommendationEnginePluginBase {

  /**
   * Default recommendation reason.
   */
  const DEFAULT_RECOMMENDATION_REASON = 'popular';
  /**
   * The database.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Statistics service.
   *
   * @var \Drupal\statistics\NodeStatisticsDatabaseStorage
   */
  protected $statisticsService;

  /**
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('Trending Content Recommendation Engine'),
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('statistics.storage.node')
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
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    NodeStatisticsDatabaseStorage $statisticsService
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory, $language_manager, $entity_type_manager);
    $this->database = $database;
    $this->statisticsService = $statisticsService;
  }

  /**
   * {@inheritdoc}
   */
  public function generateCandidates(AccountInterface $user) {
    $recommendations = [];
    $language = $user->getPreferredLangcode();
    $langcodes = [
      'zxx' => 'zxx',
      'und' => 'und',
      $language => $language,
    ];
    // Get $count new nodes and return them.
    $collection = new ViewCollection($this->getNumberOfCandidates());
    $collection->addViewById('popular_content_block', [], 'embed_1', $langcodes);
    $data = $collection->groupedResults();
    if (empty($data)) {
      return $recommendations;
    }
    $position = 1;
    foreach ($data[0]['content'] as $node) {
      $recommendations[$node->id()] = $node;
      // Store our score for later.
      // We will associate this with the candidate during the scoring phase.
      $this->updateOrCreateScoreEntity($user, $node->id(), $this->getScore($node, $position), RecommendationPluginScore::STATUS_PROCESSING);
    }
    return $recommendations;
  }

  /**
   * {@inheritdoc}
   */
  public function scoreCandidates(array $candidates, AccountInterface $user) {
    foreach ($candidates as $id => $candidate) {
      if (!($candidate instanceof RecommendationCandidate)) {
        $type = $candidate->gettype();
        throw new RecommendationEngineException("Recommendation Engine - Score Candidates - '$type' - Only Recommendation Candidate objects can be sent to plugin to be scored");
      }
      $node = $candidate->nid->entity;
      if ($node) {
        $score = $this->getScoreEntity($user->id(), $node->id());
        // Only create scores entities for non zero values.
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
   * Score is inversely proportional to time since last change.
   */
  protected function getScore(NodeInterface $node, $position) {
    if ($position === 0) {
      return 0;
    }
    return 1 / $position;
  }



}
