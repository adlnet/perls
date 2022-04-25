<?php

/**
 * @file
 * Contains Views hooks.
 */

/**
 * Implements hook_views_data_alter().
 */
function group_membership_views_data_alter(array &$data) {
  $data['groups_field_data']['group_membership'] = [
    'title' => t('Current user membership'),
    'help' => t('Relate group membership for the current user.'),

    'relationship' => [
      'base' => 'group_content_field_data',
      'base field' => 'gid',
      'field' => 'id',
      'id' => 'group_current_user_membership',
      'label' => t('Current user membership'),
    ],
  ];
}
