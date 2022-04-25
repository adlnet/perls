<?php

namespace Drupal\recommender\Plugin\Recommendations;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\recommender\Entity\RecommendationCandidate;
use Drupal\recommender\RecommendationEngineException;
use Drupal\recommender\RecommendationEnginePluginBase;
use Drupal\tools\ViewCollection;

/**
 * New Content Recommendation engine plugin.
 *
 * @RecommendationEnginePlugin(
 *   id = "new_content_recommendation_plugin",
 *   label = @Translation("New Content Recommendation Plugin"),
 *   description = @Translation("Recommends new content to users."),
 *   stages = {
 *     "generate_candidates" = 0,
 *     "score_candidates" = 0,
 *   }
 * )
 */
class NewContentRecommendationEnginePlugin extends RecommendationEnginePluginBase {

  /**
   * Default recommendation reason.
   */
  const DEFAULT_RECOMMENDATION_REASON = 'new';

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
    $collection->addViewById('content_recent', [], 'embed_1', $langcodes);
    $data = $collection->groupedResults();
    if (empty($data)) {
      return $recommendations;
    }
    foreach ($data[0]['content'] as $node) {
      $recommendations[$node->id()] = $node;
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
        $candidate_score = $this->getScore($node);
        if ($candidate_score < 0.01) {
          continue;
        }
        $score = $this->updateOrCreateScoreEntity($user, $node->id(), $candidate_score);
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
   * Score is inversely proportional to time since last change.
   */
  protected function getScore(NodeInterface $node) {
    $time = \Drupal::time()->getCurrentTime();
    $updated = $node->changed->value;
    // Difference in days.
    $diff = ($time - $updated) / (60 * 60 * 24);
    return 1 / ($diff + 1);
  }

}
