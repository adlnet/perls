<?php

namespace Drupal\veracity_vql\Plugin\VqlPreProcess;

use Drupal\Core\Form\FormStateInterface;

/**
 * Filters results by a context activity ID.
 *
 * @VqlPreProcess(
 *   id = "filter_by_context",
 *   label = "Filter by Context Activity",
 *   description = "Filters results by a context activity ID.",
 * )
 */
class ContextActivityFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'context' => 'grouping',
      'activity_id' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = $this->getConfiguration() + $this->defaultConfiguration();

    $options = ['parent', 'category', 'grouping', 'other'];
    $form['context'] = [
      '#type' => 'select',
      '#title' => $this->t('Context Activity Type'),
      '#options' => array_combine($options, $options),
      '#default_value' => $config['context'],
    ];
    $form['activity_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Activity ID'),
      '#default_value' => $config['activity_id'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilter(): array {
    $filter['context']['contextActivities'][$this->configuration['context']]['id'] = $this->getActivityId();
    return $filter;
  }

  /**
   * Gets the activity ID to filter by.
   *
   * @return string|null
   *   The activity ID.
   */
  protected function getActivityId(): ?string {
    return $this->configuration['activity_id'];
  }

}
