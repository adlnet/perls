<?php

namespace Drupal\veracity_vql\Plugin\VqlPreProcess;

use Drupal\Core\Form\FormStateInterface;

/**
 * Filters results by a relative date.
 *
 * @VqlPreProcess(
 *   id = "filter_by_date_range",
 *   label = "Filter by Date range",
 *   description = "Filters results by a relative date.",
 * )
 */
class DateRangeFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'relative_timestamp' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = $this->getConfiguration() + $this->defaultConfiguration();

    $form['relative_timestamp'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date range'),
      '#description' => $this->t('How far back should the LRS be queried? For example: <em>3 months</em> or <em>2 weeks</em>.'),
      '#default_value' => $config['relative_timestamp'],
      '#placeholder' => $this->t('3 Months'),
      '#states' => [
        'required' => [
          ':input[name="settings[pre_process][filter_by_date_range][status]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilter(): array {
    if ($startdate = $this->getStartDate()) {
      $filter['timestamp']['$gt']['$parseDate']['date'] = $startdate;
    }
    return $filter;
  }

  /**
   * Gets the date.
   *
   * @return string|null
   *   The activity ID.
   */
  protected function getStartDate(): ?string {
    if (!empty($relative_timestamp = $this->configuration['relative_timestamp'])) {
      $relative_timestamp = strtotime($relative_timestamp . " ago");
      return date_format(date_timestamp_set(new \DateTime(), $relative_timestamp), 'c');
    }

    return NULL;
  }

}
