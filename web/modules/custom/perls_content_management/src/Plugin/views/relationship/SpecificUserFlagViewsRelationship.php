<?php

namespace Drupal\perls_content_management\Plugin\views\relationship;

use Drupal\flag\Plugin\views\relationship\FlagViewsRelationship;
use Drupal\Core\Form\FormStateInterface;

/**
 * Enhances the flag relationship to relate to user from URL arg.
 *
 * @ViewsRelationship("flag_relationship")
 */
class SpecificUserFlagViewsRelationship extends FlagViewsRelationship {

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['user_scope']['#options']['url_arg'] = $this->t('User from URL argument');
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if ($this->options['user_scope'] == 'url_arg') {
      $this->definition['extra'][] = [
        'field' => 'uid',
        'value' => '***PERLS_USER_URL_ARG***',
        'numeric' => TRUE,
      ];
    }
    parent::query();
  }

}
