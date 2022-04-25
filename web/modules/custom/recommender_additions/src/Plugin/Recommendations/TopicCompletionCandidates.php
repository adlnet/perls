<?php

namespace Drupal\recommender_additions\Plugin\Recommendations;

use Drupal\recommender_additions\ViewsCandidateSourceBase;

/**
 * Generates candidates from topics that are nearly complete.
 *
 * @RecommendationEnginePlugin(
 *   id = "uncompleted_topics_recommendation_plugin",
 *   label = @Translation("Topic Completion Recommendation Plugin"),
 *   description = @Translation("Recommends content from topics that the user has nearly completed."),
 *   stages = {
 *     "generate_candidates" = 0,
 *     "score_candidates" = 0,
 *   }
 * )
 */
class TopicCompletionCandidates extends ViewsCandidateSourceBase {
  use ResetsPastCandidatesTrait;
  use ScoringViewTrait;
  use RecommendationReasonFieldTrait;

  /**
   * Default recommendation reason.
   */
  const DEFAULT_RECOMMENDATION_REASON = 'You are close to completing this topic';

  /**
   * {@inheritdoc}
   */
  protected function getViewId(): string {
    return 'uncompleted_topics_contents';
  }

  /**
   * {@inheritdoc}
   */
  protected function getScoringViewId(): string {
    return 'topics_user_progress';
  }

  /**
   * {@inheritdoc}
   */
  protected function getForeignKeyFieldName(): string {
    return 'tid';
  }

}
