<?php

namespace Drupal\perls_content_management\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Plugin\views\filter\Name;

/**
 * Custom user filter using the field_name instead of username.
 *
 * @ViewsFilter("display_name")
 */
class DisplayName extends Name {

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    // Display Name only allows autocomplete multiple so we "reset" the option.
    $this->alwaysMultiple = FALSE;
    $form['value']['#autocomplete'] = TRUE;
    $form['value']['#type'] = 'select2';
    $form['value']['#title'] = $this->t('User Display Name');
    $form['value']['#multiple'] = isset($this->options['expose']['multiple']) ? $this->options['expose']['multiple'] : FALSE;
    $form['value']['#select2'] = [
      'placeholder' => '- ' . $this->t('Any') . ' -',
    ];
    $label = ($this->options['expose']['label']) ?? $this->t('Name');
    $form['value']['#attributes']['data-min-input-text'] = $this->t('Type to filter by @label', ['@label' => $label]);
    $form['value']['#attributes']['data-no-results-text'] = $this->t('@label not found', ['@label' => $label]);
    $form['value']['#selection_handler'] = 'default:display_name_selection';
    // Prevent unuseful description from being displayed related to parent.
    if ($form_state->get('exposed')
    && !isset($user_input[$this->options['expose']['description']])) {
      unset($form['value']['#description']);
    }
  }

}
