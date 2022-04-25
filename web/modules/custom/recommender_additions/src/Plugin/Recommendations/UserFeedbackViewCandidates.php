<?php

namespace Drupal\recommender_additions\Plugin\Recommendations;

use Drupal\views\ResultRow;
use Drupal\node\NodeInterface;
use Drupal\views\ViewExecutable;
use Drupal\recommender_additions\ViewsCandidateSourceBase;

/**
 * Generates candidates based on highly rated content.
 *
 * @RecommendationEnginePlugin(
 *   id = "user_feedback_recommendation_plugin",
 *   label = @Translation("Highly-rated Content Recommendation Plugin"),
 *   description = @Translation("Recommends content based on feedback from other users."),
 *   stages = {
 *     "generate_candidates" = 0,
 *     "score_candidates" = 0,
 *   }
 * )
 */
class UserFeedbackViewCandidates extends ViewsCandidateSourceBase {
  use ResetsPastCandidatesTrait;
  use ScoringFieldTrait;

  /**
   * Default recommendation reason.
   */
  const DEFAULT_RECOMMENDATION_REASON = 'This content received positive feedback from other learners';

  /**
   * {@inheritdoc}
   */
  protected function getViewId(): string {
    return 'learner_feedback_recommendation_score';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRowCandidate(ViewExecutable $view, ResultRow $row): ?NodeInterface {
    $field = reset($view->field);
    return $field->getEntity($row);
  }

}
