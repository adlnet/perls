<?php

namespace Drupal\recommender_additions;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Base class for progress filter plugin.
 */
class ProgressViewFilter extends FilterPluginBase {

  /**
   * DB table field which is an entity reference.
   *
   * @var string
   */
  protected $referenceField = '';

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return ">=" . $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 99,
      '#field_suffix' => '%',
      '#title' => $this->t('Minimum progress'),
      '#description' => $this->t('The result will not show topics with less progress. The value should be between 1 and 99%.'),
      '#default_value' => empty($this->options['value']) ? 70 : $this->value,
      '#required' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    // @todo Make it configurable.
    $this->query->addHavingExpression(
      1,
      "SELECT ((COUNT(flagging_node_field_data.uid)/COUNT($this->referenceField.bundle))) BETWEEN :min AND :max",
      [
        ':min' => (float) ($this->value / 100),
        ':max' => 0.99,
      ]
    );
  }

}
