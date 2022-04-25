<?php

namespace Drupal\xapi\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configure xAPI content settings for this site.
 */
class XapiContentFileAdminSettingsResetForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xapi_admin_settings_reset';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('<strong>Changing the LRS endpoint may cause data loss for your learners unless you have already synced statements and document stores with your new LRS.
 Are you sure you want to proceed?</strong>');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('xapi.admin_settings_form');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('xapi.settings');
    $config->delete();
    \Drupal::messenger()->addWarning($this->t('The values were reset to system default.'));
    $form_state->setRedirect('xapi.admin_settings_form');
  }

}
