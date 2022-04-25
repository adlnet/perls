<?php

/**
 * @file
 * Contains history_views.views.inc.
 */

/**
 * Implements hook_views_data().
 */
function history_views_views_data() {
  $data = [];
  // Base data.
  $data['history']['table']['group'] = t('User history');
  $data['history']['table']['base'] = [
    'field' => 'nid',
    'title' => t('History'),
    'help' => t('History table as base table.'),
    'weight' => -10,
  ];

  // History user id field, exposed as a field, sort, filter, and argument.
  $data['history']['uid'] = [
    'title' => t('Viewed by'),
    'help' => t('The user who has viewed this content.'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'user_name',
    ],
    'argument' => [
      'id' => 'user_uid',
    ],
  ];

  $data['history']['timestamp'] = [
    'title' => t('Last viewed'),
    'help' => t('When the user last viewed the content.'),
    'field' => [
      'id' => 'date',
    ],
    'sort' => [
      'id' => 'date',
    ],
    'filter' => [
      'id' => 'date',
    ],
    'argument' => [
      'id' => 'date',
    ],
  ];

  $data['history']['nid'] = [
    'title' => t('Content ID'),
    'help' => t('The ID of the content viewed by the user.'),
    'field' => [
      'id' => 'node',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'node_nid',
    ],
  ];

  return $data;
}

/**
 * Implements hook_views_data_alter().
 */
function history_views_views_data_alter(array &$data) {
  $entity_type_manager = \Drupal::entityTypeManager();

  $entity_type = $entity_type_manager->getDefinition('node');
  if ($entity_type->hasHandlerClass('views_data')) {
    $base_table = $entity_type_manager->getHandler('node', 'views_data')->getViewsTableForEntityType($entity_type);
    $data[$base_table]['history_relationship'] = [
      'title' => t('@entity_label history', ['@entity_label' => $entity_type->getLabel()]),
      'help' => t('Limit results to only those nodes which has history records.'),
      'relationship' => [
        'group' => t('History'),
        'label' => t('History records'),
        'base' => 'history',
        'base field' => 'nid',
        'relationship field' => $entity_type->getKey('id'),
        'id' => 'history_relationship',
      ],
    ];
  }
}
