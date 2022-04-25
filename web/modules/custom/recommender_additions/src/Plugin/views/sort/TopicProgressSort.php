<?php

namespace Drupal\recommender_additions\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Sorts entities by percentage completed in a view.
 *
 * @ingroup views_sort_handlers
 * @ViewsSort("topic_progress_sort")
 */
class TopicProgressSort extends SortPluginBase {

  /**
   * Provide a list of options for the default sort form.
   *
   * Should be overridden by classes that don't override sort_form.
   */
  protected function sortOptions() {
    return [
      'ASC' => $this->t('Lowest percentage first'),
      'DESC' => $this->t('Highest percentage first'),
    ];
  }

  /**
   * Display whether or not the sort order is ascending or descending.
   */
  public function adminSummary() {
    if (!empty($this->options['exposed'])) {
      return $this->t('Exposed');
    }
    // Get the labels defined in sortOptions().
    $sort_options = $this->sortOptions();
    return $sort_options[strtoupper($this->options['order'])];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $params = [
      'function' => 'count',
    ];
    $this->query->addField(NULL, 'flagging_node_field_data.uid)/COUNT(node__field_topic.bundle', 'percentage', $params);
    $this->query->addOrderBy(NULL, NULL, $this->options['order'], "percentage");
  }

}
