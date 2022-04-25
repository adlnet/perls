<?php

namespace Drupal\recommender\Plugin\Recommendations;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\recommender\Entity\RecommendationCandidate;
use Drupal\recommender\RecommendationEngineException;
use Drupal\recommender\RecommendationEnginePluginBase;

;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Random Recommendation engine plugin.
 *
 * @RecommendationEnginePlugin(
 *   id = "random_recommendation_plugin",
 *   label = @Translation("Random Recommendation Plugin"),
 *   description = @Translation("Recommends Random content to users."),
 *   stages = {
 *     "generate_candidates" = 0,
 *     "score_candidates" = 0,
 *   }
 * )
 */
class RandomRecommendationEnginePlugin extends RecommendationEnginePluginBase {
  /**
   * Default recommendation reason.
   */
  const DEFAULT_RECOMMENDATION_REASON = 'something different';

  /**
   * The database.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('Random Recommendation Engine'),
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('database'),
      $container->get('entity_type.manager')
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
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory, $language_manager, $entity_type_manager);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function generateCandidates(AccountInterface $user) {
    $recommendations = [];
    // Get $count random nodes and return them.
    $query = $this->database->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('status', '1')
      ->range(0, 10)
      ->orderRandom();
    $results = $query->execute()->fetchCol();

    if (!empty($results)) {
      $recommendations = $this->entityTypeManager->getStorage('node')->loadMultiple($results);
    }
    return $recommendations;
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
        $score = $this->updateOrCreateScoreEntity($user, $node->id(), $this->getScore());
        $candidate->scores[] = $score;
        $candidate->save();
      }
      else {
        throw new RecommendationEngineException("Recommendation Engine - Score Candidates - Node not found");
      }
    }
  }

  /**
   * Calculate Score.
   *
   * Since this is the random recommendation engine it
   * simply returns a random number.
   */
  protected function getScore() {
    return mt_rand(0, 1000) / 1000;
  }

}
