<?php

namespace Drupal\recommender_additions\Plugin\Recommendations;

use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\recommender_additions\ViewResultAccessorTrait;

/**
 * Leverages a field from the view result as the score for a candidate.
 *
 * `getScoringFieldName` can be overridden to provide the name of the field
 * containing the score. By default, "score" is used.
 *
 * Implements `getRowScore` for `ViewCandidateSourceBase`.
 *
 * @see \Drupal\recommender_additions\ViewsCandidateSourceBase
 */
trait ScoringFieldTrait {
  use ViewResultAccessorTrait;

  /**
   * The name of the field containing the candidate score.
   *
   * The view providing the candidates must contain this field.
   *
   * If this value does not exactly match a field ID (as determined by views),
   * a field with a matching label or admin label will be used.
   *
   * @return string
   *   The name of the field containing the score.
   */
  protected function getScoringFieldName(): string {
    return 'score';
  }

  /**
   * Retrieves the recommendation candidate score for a row of the view result.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view being used to generate recommendations.
   * @param \Drupal\views\ResultRow $row
   *   A result row from executing the view.
   *
   * @return float
   *   The score for the row; returns 0 if the score field cannot be found.
   */
  protected function getRowScore(ViewExecutable $view, ResultRow $row): float {
    if ((!$field_id = $this->findFieldId($view, $this->getScoringFieldName()))) {
      return 0;
    }

    $field_values = $this->getRowFieldValues($view, $row);

    if (!isset($field_values[$field_id])) {
      return 0;
    }

    return (float) (string) $field_values[$field_id];
  }

}
