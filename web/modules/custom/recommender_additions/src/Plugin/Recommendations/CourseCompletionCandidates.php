<?php

namespace Drupal\recommender_additions\Plugin\Recommendations;

use Drupal\views\ResultRow;
use Drupal\node\NodeInterface;
use Drupal\views\ViewExecutable;
use Drupal\recommender_additions\ViewsCandidateSourceBase;

/**
 * Generates candidates for courses that are nearly complete.
 *
 * @RecommendationEnginePlugin(
 *   id = "uncompleted_course_recommendation_plugin",
 *   label = @Translation("Course Completion Recommendation Plugin"),
 *   description = @Translation("Recommends courses to users that they have nearly completed."),
 *   stages = {
 *     "generate_candidates" = 0,
 *     "score_candidates" = 0,
 *   }
 * )
 */
class CourseCompletionCandidates extends ViewsCandidateSourceBase {
  use ResetsPastCandidatesTrait;
  use ScoringFieldTrait;

  /**
   * Default recommendation reason.
   */
  const DEFAULT_RECOMMENDATION_REASON = 'You are close to completing this course';

  /**
   * {@inheritdoc}
   */
  protected function getViewId(): string {
    return 'user_course_progress_score';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRowCandidate(ViewExecutable $view, ResultRow $row): ?NodeInterface {
    return $view->field['title_1']->getEntity($row);
  }

}
