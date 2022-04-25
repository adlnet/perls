<?php

namespace Drupal\recommender_additions\Plugin\Recommendations;

use Drupal\views\Views;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Session\AccountInterface;

/**
 * Leverages two views for generating and scoring candidates.
 *
 * The scoring view is used to prepare scores and provide view arguments
 * for the candidates view.
 *
 * Both the scoring view and candidates view must have cooresponding views
 * with the same name, called the "foreign key." The foreign key from the
 * scoring view is used as the view arguments for the candidates view.
 * The candidates view should include the same field so each candidate can
 * be associated with a score.
 *
 * Implements `getViewArguments` and `getRowScore`
 * for `ViewsCandidateSourceBase`.
 *
 * @see \Drupal\recommender_additions\ViewsCandidateSourceBase
 */
trait ScoringViewTrait {
  use ScoringFieldTrait {
    getRowScore as getScore;
  }

  /**
   * Stores computed scores to be referenced during candidate generation.
   *
   * @var array
   */
  private $scores = [];

  /**
   * The ID of the view to use for retrieving scores.
   *
   * @return string
   *   A view ID.
   */
  abstract protected function getScoringViewId(): string;

  /**
   * The name of the field containing the foreign key.
   *
   * Both the scoring view and candidates view must have this field.
   *
   * If this value does not exactly match a field ID (as determined by views),
   * a field with a matching label or admin label will be used.
   *
   * @return string
   *   The field name to use as the foreign key.
   */
  abstract protected function getForeignKeyFieldName(): string;

  /**
   * Arguments to pass to the view prior to generating candidates.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user receiving recommendations.
   *
   * @return array
   *   Arguments to pass to the view.
   */
  protected function getViewArguments(AccountInterface $account): array {
    $view = Views::getView($this->getScoringViewId());

    $this->renderer->executeInRenderContext(new RenderContext(), function () use ($view) {
      $view->execute();

      $this->scores = array_reduce($view->result, function ($scores, $row) use ($view) {
        $field_values = $this->getRowFieldValues($view, $row);
        if (($foreign_key_field_id = $this->findFieldId($view, $this->getForeignKeyFieldName()))) {
          $foreign_key = (string) $field_values[$foreign_key_field_id];
          $scores[$foreign_key] = $this->getScore($view, $row);
        }

        return $scores;
      }, []);
    });

    return [
      implode(',', array_keys($this->scores)),
    ];
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
   *   The score for the row.
   */
  protected function getRowScore(ViewExecutable $view, ResultRow $row): float {
    $foreign_key_field_id = $this->findFieldId($view, $this->getForeignKeyFieldName());
    if (!$foreign_key_field_id) {
      return 0;
    }

    $field_values = $this->getRowFieldValues($view, $row);

    $foreign_key = (string) $field_values[$foreign_key_field_id];
    return $this->scores[$foreign_key] ?? 0;
  }

}
