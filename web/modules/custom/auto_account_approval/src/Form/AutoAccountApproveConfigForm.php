<?php

namespace Drupal\auto_account_approval\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\multivalue_form_element\Element\MultiValue;

/**
 * Provides a setting form to Auto account approval module.
 */
class AutoAccountApproveConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'auto_account_approval_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['auto_account_approval.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('auto_account_approval.settings');

    $form['top_domain_whitelist'] = [
      '#type' => 'multivalue',
      '#orderable' => FALSE,
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      '#title' => $this->t('Whitelisted Email Domains'),
      '#description' => $this->t('Specify domain names of email addresses that should be automatically approved for new accounts. For example, to automatically approve anyone with an @example.com email address, add "example.com".'),
      '#description_display' => 'before',
      'domain' => [
        '#type' => 'textfield',
        '#title' => $this->t('Domain'),
      ],
      '#default_value' => explode(',', $config->get('top_level_domains')),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $domain_list = array_column($form_state->getValue('top_domain_whitelist'), 'domain');
    $this->config('auto_account_approval.settings')
      ->set('top_level_domains', implode(',', $domain_list))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
