<?php

namespace Drupal\recommender\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements a Batch example Form.
 */
class GetUserRecommendationsBatchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'get_user_recommendations_batch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['details'] = [
      '#markup' => '<h2>Are you sure you want to build/queue recommendations for all users?</h2><p>This process will create recommendations for all users in the system and can take a long time, depending on the number of users in the system. You can choose to queue users for recommendations and they will be calculated during cron runs.</p>',
    ];

    $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Build Recommendations for all users now.'),
      '#name' => 'build_recommendations',
    ];
    $form['queue_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Queue users for recommendations.'),
      '#name' => 'queue_recommendations',
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
      case 'queue_recommendations':
        \Drupal::service('recommender.recommendation_service')->buildAllUserRecommendations();
        $form_state->setRedirect('recommender.admin_settings_form');
        break;

      default:
        \Drupal::service('recommender.recommendation_service')->buildAllUserRecommendations(TRUE);
        $form_state->setRedirect('recommender.admin_settings_form');
        break;
    }

  }

}
