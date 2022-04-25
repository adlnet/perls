<?php

namespace Drupal\recommender\Plugin\Recommendations;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;

;
use Drupal\recommender\Entity\RecommendationCandidate;
use Drupal\recommender\Entity\RecommendationPluginScore;
use Drupal\recommender\RecommendationEngineException;
use Drupal\recommender\RecommendationEnginePluginBase;
use Drupal\recommender\SimilarContentViewCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Similar Content Recommendation engine plugin.
 *
 * @RecommendationEnginePlugin(
 *   id = "similar_content_recommendation_plugin",
 *   label = @Translation("Similar Content Recommendation Plugin"),
 *   description = @Translation("Recommends content that is similar to content already completed by the user, using Solr more like this module."),
 *   stages = {
 *     "generate_candidates" = 0,
 *     "score_candidates" = 0,
 *   }
 * )
 */
class SimilarContentRecommendationEnginePlugin extends RecommendationEnginePluginBase {

  /**
   * Default recommendation reason.
   */
  const DEFAULT_RECOMMENDATION_REASON = 'similar to content you’ve completed';
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
      $container->get('logger.factory')->get('Trending Content Recommendation Engine'),
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('database')
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
    Connection $database
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory, $language_manager, $entity_type_manager);
    $this->database = $database;
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

    // Get a list of all completed content:
    $query = $this->database->select('flagging', 'f')
      ->fields('f', ['entity_id'])
      ->condition('entity_type', 'node')
      ->condition('uid', $user->id())
      ->condition('flag_id', 'completed')
      ->orderBy('created', 'DESC');
    $completions = $query->execute()->fetchCol();

    if (empty($completions)) {
      return $recommendations;
    }
    $scores = [];
    $nodes = [];
    // Look for similar content until you get enough candidates.
    $recommendations_found = 0;
    foreach ($completions as $completion_id) {
      $collection = new SimilarContentViewCollection($this->getNumberOfCandidates());
      $collection->addViewById('more_like_this', ['nid' => $completion_id], 'embed_1', $langcodes);
      $data = $collection->groupedResults();
      if (empty($data)) {
        continue;
      }
      $number_similar_to_this = 0;
      foreach ($data as $node_id => $item) {
        $node = $item['node'];
        // Check to see if it was previously completed.
        if (in_array($node->id(), $completions)) {
          continue;
        }
        // Check that node is top level object.
        if ($node->hasField('related_courses')) {
          // If it has a parent we recommend the parent instead.
          $parents = $node->related_courses->referencedEntities();
          if (!empty($parents)) {
            $node = $item['node'] = reset($parents);
          }
        }
        // If we get to here we have a valid recommendation.
        // It may have already been add so check here and keep biggest score.
        if (isset($nodes[$node->id()])) {
          // Already added so update score.
          if ($item['score'] > $scores[$node->id()]) {
            $scores[$node->id()] = $item['score'];
          }
        }
        else {
          // This is a new recommendation we can add.
          $scores[$node->id()] = $item['score'];
          $nodes[$node->id()] = $node;
          $number_similar_to_this++;
          $recommendations_found++;
          // Each article can only give 3 recommendations.
          if ($number_similar_to_this >= 3) {
            break;
          }
        }
      }
      if ($recommendations_found >= $this->getNumberOfCandidates()) {
        break;
      }
    }

    if (empty($scores)) {
      return $recommendations;
    }
    // Get max score.
    $max_score = max($scores);

    foreach ($scores as $nid => $score) {
      $node = $nodes[$nid];
      // Add this to our recommendation.
      $recommendations[$node->id()] = $node;
      // Store our score for later.
      // We will associate this with the candidate during the scoring phase.
      $this->updateOrCreateScoreEntity($user, $node->id(), $score / $max_score, RecommendationPluginScore::STATUS_PROCESSING);
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
   * Get Previously Stored score entities.
   */
  protected function getScoreEntity($user_id, $node_id) {
    $entity = $this->entityTypeManager
      ->getStorage('recommendation_plugin_score')
      ->loadByProperties(
        [
          'user_id' => $user_id,
          'nid' => $node_id,
          'plugin_id' => $this->getPluginId(),
          'status' => RecommendationPluginScore::STATUS_PROCESSING,
        ]
      );

    if (!empty($entity)) {
      $entity = reset($entity);
      $entity->setStatus(RecommendationPluginScore::STATUS_READY);
      $entity->save();
      return $entity;
    }
    else {
      return FALSE;
    }
  }

}
