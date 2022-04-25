<?php

namespace Drupal\perls_user;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Helper class for status report page.
 */
class StatusReportPage implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

  /**
   * Renders displays of User Status Reports view in general info section.
   */
  public static function preRender($element) {
    $element['#general_info']['#users_logged_in_past_two_weeks'] = [
      '#type' => 'view',
      '#name' => 'user_status_reports',
      '#display_id' => 'users_logged_in_past_two_weeks',
    ];

    $element['#general_info']['#number_of_learners'] = [
      '#type' => 'view',
      '#name' => 'user_status_reports',
      '#display_id' => 'number_of_learners',
    ];

    $element['#general_info']['#number_of_content_managers'] = [
      '#type' => 'view',
      '#name' => 'user_status_reports',
      '#display_id' => 'number_of_content_managers',
    ];

    $element['#general_info']['#number_of_administrators'] = [
      '#type' => 'view',
      '#name' => 'user_status_reports',
      '#display_id' => 'number_of_administrators',
    ];

    $element['#general_info']['#number_of_all_users'] = [
      '#type' => 'view',
      '#name' => 'user_status_reports',
      '#display_id' => 'number_of_all_users',
    ];

    return $element;
  }

}
