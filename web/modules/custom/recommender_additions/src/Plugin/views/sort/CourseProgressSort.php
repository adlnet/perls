<?php

namespace Drupal\recommender_additions\Plugin\views\sort;

use Drupal\recommender_additions\ProgressViewSort;

/**
 * Sorts entities by percentage completed in a view.
 *
 * @ingroup views_sort_handlers
 * @ViewsSort("course_progress_sort")
 */
class CourseProgressSort extends ProgressViewSort {

  /**
   * {@inheritdoc}
   */
  protected $referenceField = 'node__field_learning_content';

}
