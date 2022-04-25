<?php

namespace Drupal\recommender_additions\Plugin\Recommendations;

use Drupal\Core\Session\AccountInterface;

/**
 * Includes past candidates after clearing out previous scores.
 *
 * This ensures that the plugin re-scores past candidates.
 *
 * @see \Drupal\recommender\RecommendationEnginePluginBase
 */
trait ResetsPastCandidatesTrait {

  /**
   * {@inheritdoc}
   */
  public function generateCandidates(AccountInterface $user) {
    $scores = $this->retrievePastScores($user);
    $past_nodes = $this->retrieveNodes($scores);
    $this->resetScores($scores);

    return parent::generateCandidates($user) + $past_nodes;
  }

  /**
   * Retrieves existing scores for the user on this plugin.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user receiving recommendations.
   *
   * @return \Drupal\recommender\Entity\RecommendationPluginScore[]
   *   The past scores from this plugin.
   */
  protected function retrievePastScores(AccountInterface $user): array {
    return $this->entityTypeManager
      ->getStorage('recommendation_plugin_score')
      ->loadByProperties([
        'user_id' => $user->id(),
        'plugin_id' => $this->getPluginId(),
      ]);
  }

  /**
   * Retrieves the nodes associated with the scores, keyed by node ID.
   *
   * @param \Drupal\recommender\Entity\RecommendationPluginScore[] $scores
   *   Past plugin scores.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Nodes associated with the plugin scores.
   */
  protected function retrieveNodes(array $scores): array {
    $candidates = [];
    foreach ($scores as $score) {
      $node = $score->nid->entity;
      if (!$node) {
        continue;
      }

      $candidates[$node->id()] = $node;
    }
    return $candidates;
  }

  /**
   * Resets/clears the provided plugin scores.
   *
   * @param \Drupal\recommender\Entity\RecommendationPluginScore[] $scores
   *   Scores to delete.
   */
  protected function resetScores(array $scores) {
    $this->entityTypeManager
      ->getStorage('recommendation_plugin_score')
      ->delete($scores);
  }

}
