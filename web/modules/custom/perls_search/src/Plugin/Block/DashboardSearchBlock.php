<?php

namespace Drupal\perls_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Block for the dashboard form.
 *
 * @Block(
 *   id = "perls_views_search_block",
 *   admin_label = @Translation("Search Block for Perls Dashboard")
 * )
 */
class DashboardSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build_params = [
      'display_id' => $this->configuration['views_display'],
      'views_id' => $this->configuration['views_id'],
    ];
    $form = \Drupal::formBuilder()->getForm('\Drupal\perls_search\Form\PerlsDashboardSearchForm', $build_params);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['views_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Views id'),
      '#decsription' => $this->t('This view will provide the search result'),
      '#default_value' => isset($this->configuration['views_id']) ? $this->configuration['views_id'] : '',
      '#size' => 64,
    ];

    $form['views_display'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Views display'),
      '#decsription' => $this->t('This view display will provide the search result'),
      '#default_value' => isset($this->configuration['views_display']) ? $this->configuration['views_display'] : '',
      '#size' => 64,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['views_id'] = $form_state->getValue('views_id');
    $this->configuration['views_display'] = $form_state->getValue('views_display');
  }

}
