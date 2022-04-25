<?php

namespace Drupal\veracity_vql\Plugin\VqlPreProcess;

use Drupal\Core\Form\FormStateInterface;
use Drupal\veracity_vql\Plugin\VqlPreProcessBase;

/**
 * Filters results by a relative date.
 *
 * @VqlPreProcess(
 *   id = "process_by_timezone",
 *   label = "Process by timezone",
 *   description = "Process results with timezone.",
 * )
 */
class UserTimezoneProcess extends VqlPreProcessBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'timezone' => date_default_timezone_get(),
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = $this->getConfiguration() + $this->defaultConfiguration();

    $form['timezone'] = [
      '#type' => 'select',
      '#options' => \system_time_zones(TRUE, TRUE),
      '#title' => $this->t('Timezones'),
      '#description' => $this->t('Timezones.'),
      '#default_value' => $config['timezone'],
      '#empty_option' => $this->t('- Current user time zone -'),
      '#states' => [
        'required' => [
          ':input[name="settings[pre_process][process_by_timezone][status]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterQuery(array &$query) {
    $jsonString = json_encode($query);
    // If the $def timezone exists in VQL.
    $string = '"timezone":"UTC"';
    if (strpos($jsonString, $string, 1)) {
      $timeZone = !empty($this->configuration['timezone']) ? $this->configuration['timezone'] : date_default_timezone_get();
      $jsonString = str_replace($string, '"timezone":"' . $timeZone . '"', $jsonString);
      $query = json_decode($jsonString, TRUE);
    }
  }

}
