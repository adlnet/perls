<?php

namespace Drupal\recommender_additions\Plugin\Recommendations;

use Drupal\Core\Form\FormStateInterface;
use Drupal\recommender_additions\ViewResultAccessorTrait;

/**
 * Leverages a views field for providing a recommendation reason.
 *
 * `getRecommendationReasonFieldName` can be overridden to provide the name
 * of the field containing the reason. By default, "reason" is used.
 *
 * @see \Drupal\recommender_additions\ViewsCandidateSourceBase
 * @see \Drupal\recommender\RecommendationEnginePluginInterface
 */
trait RecommendationReasonFieldTrait {
  use ViewResultAccessorTrait;

  /**
   * The name of the field containing the reason for the candidate.
   *
   * The view providing the candidates must contain this field.
   *
   * If this value does not exactly match a field ID (as determined by views),
   * a field with a matching label or admin label will be used.
   *
   * @return string
   *   The name of the field containing the recommendation reason.
   */
  protected function getRecommendationReasonFieldName(): string {
    return 'reason';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRecommendationReason($langcode = NULL) {
    $view = $this->currentContext['view'] ?? NULL;
    $row = $this->currentContext['row'] ?? NULL;

    if (!$view || !$row) {
      return parent::getRecommendationReason();
    }

    $field_id = $this->findFieldId($view, $this->getRecommendationReasonFieldName());
    $field_values = $this->getRowFieldValues($view, $row);

    return $field_values[$field_id] ?? parent::getRecommendationReason();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    // Hiding the recommendation reason field from the form since
    // the plugin will be using a value from the view.
    unset($form['recommendation_reason'], $form['recommendation_reason_template']);
    return $form;
  }

}
