<?php

namespace Drupal\recommender\Plugin\RecommendationScoreCombine;

use Drupal\recommender\Entity\RecommendationCandidate;
use Drupal\recommender\RecommendationScoreCombinePluginBase;

/**
 * Weighted Score Combine plugin.
 *
 * @RecommendationScoreCombinePlugin(
 *   id = "averaged_score_combine",
 *   label = @Translation("Sum"),
 *   description = @Translation("This plugin combines recommendation engine scores using an average value."),
 * )
 */
class SumScorePlugin extends RecommendationScoreCombinePluginBase {

  /**
   * {@inheritdoc}
   */
  protected function getScoresByPluginId(RecommendationCandidate $candidate): array {
    return array_reduce($candidate->get('scores')->referencedEntities(), function ($result, $score) {
      $result[$score->get('plugin_id')->value] = $score->get('score')->value;
      return $result;
    }, []);
  }

}
