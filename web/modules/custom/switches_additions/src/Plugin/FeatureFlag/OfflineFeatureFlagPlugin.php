<?php

namespace Drupal\switches_additions\Plugin\FeatureFlag;

use Drupal\switches_additions\FeatureFlagPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a condition that always evaluates to false.
 *
 * @FeatureFlag(
 *   id = "offline_support_feature",
 *   label = @Translation("Handles feature flag for offline support"),
 *   switchId = "offline_support",
 *   supportedManagerInvokeMethods = {
 *     "entityAccess",
 *     "formAlter"
 *   },
 *   weight = "2",
 * )
 */
class OfflineFeatureFlagPlugin extends FeatureFlagPluginBase {

  /**
   * {@inheritdoc}
   */
  public function featureWasDisabled() {
  }

  /**
   * {@inheritdoc}
   */
  public function featureWasEnabled() {
  }

  /**
   * {@inheritdoc}
   */
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $wget_user_id = \Drupal::state()->get('wget_user');
    if ($operation === 'view' && $this->isSwitchDisabled() && $account->id() === $wget_user_id) {
      return AccessResult::forbidden();
    }
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function formAlter(&$form, FormStateInterface $form_state) {
    if ($form['#form_id'] === 'entity_packager_page_admin_form' && $this->isSwitchDisabled()) {
      $form['general'] = [
        '#type' => 'details',
        '#title' => $this->t('Disabled'),
        '#open' => TRUE,
        '#description' => $this->t('Note: This feature is disabled'),
      ];
    }
  }

}
