<?php

namespace Drupal\recommender_additions\Plugin\views\filter;

use Drupal\recommender_additions\ProgressViewFilter;

/**
 * Add a view filter where we can show records with specific progress.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("topic_progress_filter")
 * @property \Drupal\views\Plugin\views\query\Sql $query
 */
class TopicProgressFilter extends ProgressViewFilter {

  /**
   * {@inheritdoc}
   */
  protected $referenceField = 'node__field_topic';

}
