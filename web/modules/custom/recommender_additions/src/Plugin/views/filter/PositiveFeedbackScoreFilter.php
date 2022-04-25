<?php

namespace Drupal\recommender_additions\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * A view filter which filter out content with lower score as it is in settings.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("positive_feedback_score_filter")
 * @property \Drupal\views\Plugin\views\query\Sql $query
 */
class PositiveFeedbackScoreFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return sprintf('>=%s', $this->value);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'number',
      '#title' => $this->t('Min score'),
      '#description' => $this->t('We do not show content with lower sore feedback.'),
      '#default_value' => empty($this->options['value']) ? 0 : $this->value,
      '#required' => TRUE,
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
      $this->query->addHavingExpression(
        1,
        "SELECT ((SUM(webform_submission_field_content_specific_webform_content_relevant.value)/COUNT(webform_submission_field_content_specific_webform_content_relevant.value)) + (COUNT(webform_submission_field_content_specific_webform_content_relevant.value) * 0.01)) >= :min",
        [
          ':min' => $this->value,
        ]
      );
    }
  }

}
