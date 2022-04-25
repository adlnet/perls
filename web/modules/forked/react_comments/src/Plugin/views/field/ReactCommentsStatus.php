<?php

namespace Drupal\react_comments\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;

/**
 * React Comment status
 *
 * @ViewsField("react_comment_status")
 */
class ReactCommentsStatus extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    if (!empty($this->options['not'])) {
      $value = !$value;
    }
    return $this->getStatusLabel($value);
  }

  protected function getStatusLabel($value) {
    if ($value == RC_COMMENT_PUBLISHED) {
      return $this->t('Published');
    }
    elseif ($value == RC_COMMENT_UNPUBLISHED) {
      return $this->t('Unpublished');
    }
    elseif ($value == RC_COMMENT_FLAGGED) {
      return $this->t('Flagged');
    }
    elseif ($value == RC_COMMENT_DELETED) {
      return $this->t('Deleted');
    }
  }

}
