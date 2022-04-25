<?php

/**
 * @file
 * Adds a data field for displaying a user's email address in a view.
 */

/**
 * Implements hook_views_data_alter().
 */
function user_email_access_views_data_alter(array &$data) {
  $data['users_field_data']['user_email'] = [
    'title' => t('Email address'),
    'field' => [
      'title' => t('Email address'),
      'help' => t('This is a more flexible user email address field that gives respects the permissions "view all email addresses" and "view own email address" defined by the "user_email_access" module.'),
      'id' => 'user_email',
    ],
  ];
}
