<?php

/**
 * @file
 * Views hook implementations for the Search API spellcheck module.
 */

/**
 * Implements hook_views_data().
 */
function perls_search_views_data() {
  // @todo Only enable for search api views.
  $data['views']['perls_search_spellcheck'] = [
    'title' => t('Perls search spellcheck'),
    'help' => t('Displays Perls spell check suggestions.'),
    'area' => [
      'id' => 'perls_search_spellcheck',
    ],
  ];

  return $data;
}
