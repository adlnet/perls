<?php

namespace Drupal\perls_search\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides the two input text boxes need for search page.
 *
 * @Block(
 *   id = "perls_search_input_block",
 *   admin_label = @Translation("Perls Search Input Block"),
 * )
 */
class PerlsSearchInput extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form['search_autocomplete'] = [
      '#type' => 'textfield',
      '#id' => 'search_field_autocomplete',
    ];
    $form['container'] = [
      '#type' => '#container',
      '#prefix' => '<div id = "clear_all_button_div">',
      '#suffix' => '</div>',
    ];
    $form['container']['clear_button'] = [
      '#type' => 'button',
      '#id' => 'clear_all_button',
      '#value' => ' ',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
  }

}
