<?php

namespace Drupal\recommender_additions\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Sorts content by feedbacks scores.
 *
 * @ingroup views_sort_handlers
 * @ViewsSort("positive_feedback_score_sort")
 */
class PositiveFeedbackScoreSort extends SortPluginBase {

  /**
   * Provide a list of options for the default sort form.
   *
   * Should be overridden by classes that don't override sort_form.
   */
  protected function sortOptions() {
    return [
      'ASC' => $this->t('Lowest score first'),
      'DESC' => $this->t('Highest score first'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $loaded = $this->query->ensureTable('webform_submission_field_content_specific_webform_content_relevant');
    if ($loaded) {
      // This expression assume that we filter for proper field of webform.
      $params = [
        'function' => 'sum',
      ];
      $this->query->addField(NULL, 'webform_submission_field_content_specific_webform_content_relevant.value)/COUNT(webform_submission_field_content_specific_webform_content_relevant.value) + (COUNT(webform_submission_field_content_specific_webform_content_relevant.value) * 0.01', 'webform_feedback', $params);
      $this->query->addOrderBy(NULL, NULL, $this->options['order'], 'webform_feedback');
    }
  }

}
