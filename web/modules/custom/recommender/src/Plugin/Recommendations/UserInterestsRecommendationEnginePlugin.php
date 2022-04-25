<?php

namespace Drupal\recommender\Plugin\Recommendations;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\recommender\Entity\RecommendationCandidate;
use Drupal\recommender\RecommendationEngineException;

;
use Drupal\recommender\RecommendationEnginePluginBase;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * User Interests Recommendation engine plugin.
 *
 * @RecommendationEnginePlugin(
 *   id = "user_interests_recommendation_plugin",
 *   label = @Translation("User Interests Recommendation Plugin"),
 *   description = @Translation("Recommends content based on user interests."),
 *   stages = {
 *     "generate_candidates" = 0,
 *     "score_candidates" = 0,
 *   }
 * )
 */
class UserInterestsRecommendationEnginePlugin extends RecommendationEnginePluginBase {
  /**
   * Default recommendation reason.
   */
  const DEFAULT_RECOMMENDATION_REASON = 'something you might be interested in';

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
      $container->get('logger.factory')->get('User Interests Recommendation Engine'),
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
    $user = User::load($user->id());

    // Get a list of all topics of interest.
    $query = $this->database->select('user__field_interests', 'i')
      ->fields('i', ['field_interests_target_id'])
      ->condition('bundle', 'user')
      ->condition('entity_id', $user->id());
    $topics = $query->execute()->fetchCol();
    if (empty($topics)) {
      return $recommendations;
    }

    // Get a list of completions.
    $query = $this->database->select('flagging', 'f')
      ->fields('f', ['entity_id'])
      ->condition('entity_type', 'node')
      ->condition('flag_id', 'completed')
      ->condition('uid', $user->id());
    $completions = $query->execute()->fetchCol();

    // Get all top level nodes in topics of interest that are not yet completed.
    $query = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->condition('status', '1')
      ->condition('field_topic', $topics, 'IN')
      ->condition('type',
        [
          'course',
          'learn_article',
          'learn_link',
          'learn_package',
        ],
         'IN')
      ->condition('field_course', NULL, 'IS NULL')
      ->range(0, $this->getNumberOfCandidates())
      ->addTag('sort_by_random');
    if (!empty($completions)) {
      $query->condition('nid', $completions, 'NOT IN');
    }
    $candidates = $query->execute();

    if (!empty($candidates)) {
      $recommendations = $this->entityTypeManager->getStorage('node')->loadMultiple($candidates);
    }
    return $recommendations;
  }

  /**
   * {@inheritdoc}
   */
  public function scoreCandidates(array $candidates, AccountInterface $user) {
    // Get user interests.
    // Get a list of all topics of interest.
    $query = $this->database->select('user__field_interests', 'i')
      ->fields('i', ['field_interests_target_id'])
      ->condition('bundle', 'user')
      ->condition('entity_id', $user->id());
    $topics = $query->execute()->fetchCol();

    foreach ($candidates as $nid => $candidate) {
      if (!($candidate instanceof RecommendationCandidate)) {
        $type = $candidate->gettype();
        throw new RecommendationEngineException("Recommendation Engine - Score Candidates - '$type' - Only Recommendation Candidate objects can be sent to plugin to be scored");
      }
      $node = $candidate->nid->entity;
      if ($node) {
        $score_value = $this->getScore($node, $topics);
        if ($score_value > 0) {
          $score = $this->updateOrCreateScoreEntity($user, $node->id(), $score_value);
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
   * We score all user interests between 0.75 and 1 with rand spread.
   */
  protected function getScore(NodeInterface $node, $topics) {
    // @todo use user engagment and recommendation history to improve this.
    if ($topic = $node->field_topic->referencedEntities()) {
      $topic = reset($topic);
      if (in_array($topic->id(), $topics)) {
        return mt_rand(750, 1000) / 1000;
      }
    }
    return 0;
  }

}
