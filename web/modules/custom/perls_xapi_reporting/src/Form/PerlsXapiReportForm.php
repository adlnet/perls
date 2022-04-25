<?php

namespace Drupal\perls_xapi_reporting\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration from to perls_xapi_reporting module.
 */
class PerlsXapiReportForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['perls_xapi_reporting.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'perls_xapi_reporting_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('perls_xapi_reporting.settings');

    $form['statistics_roles'] = [
      '#type' => 'container',
      '#title' => $this->t('Roles'),
    ];

    $form['statistics_roles']['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Role list'),
      '#options' => array_map([
        '\Drupal\Component\Utility\Html',
        'escape',
      ], user_role_names(TRUE)),
      '#description' => $this->t('Please select which roles needs to send user interactions to LRS. If you do not select any role all user will be tracked.'),
      '#default_value' => $config->get('roles'),
    ];

    $form['statistics_roles']['negate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Negate'),
      '#description' => $this->t('You can negate the role selection above.'),
      '#default_value' => $config->get('negate'),
    ];

    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('perls_xapi_reporting.settings');
    $config
      ->set('roles', $form_state->getValue('roles'))
      ->set('negate', $form_state->getValue('negate'))
      ->save();
    parent::submitForm($form, $form_state);

    // Invalidate node cache otherwise the roles check won't run after settings
    // change.
    Cache::invalidateTags(['node_view']);
  }

}
