<?php

namespace Drupal\react_comments\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\NumericFilter;

/**
 * React Comment status filter.
 *
 * @ViewsFilter("react_comment_status")
 */
class ReactCommentsStatus extends NumericFilter {

  public function query() {
    $this->ensureMyTable();
    if ($this->value != 'All') {
      $field = "$this->tableAlias.$this->realField";

      $info = $this->operators();
      if (!empty($info[$this->operator]['method'])) {
        $this->{$info[$this->operator]['method']}($field);
      }
    }
  }

  protected function opSimple($field) {
    if ($this->value['value'] != 'All') {
      $this->query->addWhere($this->options['group'], $field, $this->value['value'], $this->operator);
    }
  }

  /**
   * Status filter.
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Status'),
      '#options'       => $this->getStatues(),
      '#default_value' => !empty($this->value['value']) ? $this->value['value'] : '',
    ];
  }

  protected function getStatues() {
    return [
      RC_COMMENT_PUBLISHED   => $this->t('Published'),
      RC_COMMENT_UNPUBLISHED => $this->t('Unpublished'),
      RC_COMMENT_FLAGGED     => $this->t('Flagged'),
      RC_COMMENT_DELETED     => $this->t('Deleted'),
    ];
  }

}
