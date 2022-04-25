<?php

namespace Drupal\recommender_additions;

use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\Component\Utility\Html;

/**
 * Provides methods for retrieving results from a view.
 */
trait ViewResultAccessorTrait {

  /**
   * Retrieves an array of result values keyed by field ID.
   *
   * It is the caller's responsibility to ensure this is invoked
   * within a render context (otherwise the behavior is undefined).
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view that was executed.
   * @param \Drupal\views\ResultRow $row
   *   A single result row from the view.
   *
   * @return array
   *   Rendered field values from the result, keyed by the field ID.
   */
  protected function getRowFieldValues(ViewExecutable $view, ResultRow $row): array {
    return array_map(function ($field) use ($row) {
      $value = $field->advancedRender($row);
      return Html::decodeEntities($value);
    }, $view->field);
  }

  /**
   * Finds a field ID on a view cooresponding to the specified value.
   *
   * This first looks for a field with an ID matching the value exactly.
   * If no exact match is found, it then searches for a field with a
   * human or admin label that matches (case-insensitive).
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param string $query
   *   A field ID or field label to find.
   *
   * @return string|null
   *   The field ID of the matching field or NULL if no field was found.
   */
  protected function findFieldId(ViewExecutable $view, string $query): ?string {
    if (isset($view->field[$query])) {
      return $query;
    }

    foreach ($view->field as $field_id => $field) {
      if (strtolower($field->label()) === $query) {
        return $field_id;
      }

      if (strtolower($field->adminLabel()) === $query) {
        return $field_id;
      }
    }

    return NULL;
  }

}
