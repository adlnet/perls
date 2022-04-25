<?php

namespace Drupal\recommender\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements a Batch example Form.
 */
class DeleteRecommendationsBatchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'delete_recommendations_batch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['details'] = [
      '#markup' => '<h2>Are you sure you want to delete all recommendations in the system?</h2><p>Once complete there will be no recommended content in the system</p>',
    ];

    $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete all user recommendations.'),
      '#name' => 'delete_recommendations',
    ];
    $form['cancel_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel.'),
      '#name' => 'cancel',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    switch ($form_state->getTriggeringElement()['#name']) {
      case 'delete_recommendations':
        \Drupal::service('recommender.recommendation_service')->deleteUserRecommendations();
        $form_state->setRedirect('recommender.admin_settings_form');
        break;

      default:
        $form_state->setRedirect('recommender.admin_settings_form');
        break;
    }

  }

}
