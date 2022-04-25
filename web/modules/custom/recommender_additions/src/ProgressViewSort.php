<?php

namespace Drupal\recommender_additions;

use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Base class for progress sorting plugin.
 */
class ProgressViewSort extends SortPluginBase {

  /**
   * DB table field which is an entity reference.
   *
   * @var string
   */
  protected $referenceField = '';

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
    $this->query->addField(NULL, "flagging_node_field_data.uid)/COUNT($this->referenceField.bundle", 'percentage', $params);
    $this->query->addOrderBy(NULL, NULL, $this->options['order'], "percentage");
  }

}
