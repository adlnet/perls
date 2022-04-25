<?php

/**
 * @file
 * Hooks provided by the Perls Recommendation module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the available Recommendation engines.
 *
 * Modules may implement this hook to alter the information that defines
 * Recommendation Engine plugins. All properties that are available in
 * \Drupal\perls_recommendation\Annotation\RecommendationEngine can be altered
 * here, with the addition of the "class" and "provider" keys.
 *
 * @param array $re_info
 *   The Recommendation Engine info array, keyed by backend ID.
 *
 * @see \Drupal\perls_recommendation\RecommendationEnginePluginBase
 */
function hook_recommendation_engine_info_alter(array &$re_info) {
  foreach ($re_info as $id => $info) {
    $backend_info[$id]['class'] = '\Drupal\my_module\MyBackendDecorator';
    $backend_info[$id]['example_original_class'] = $info['class'];
  }
}

/**
 * Alter the recommendations returned from recommendation engines.
 *
 * This hook gives access to all suggested recommendations before
 * the entities are flagged. Changes here will reflect what nodes
 * are flagged as recommended.
 *
 * @param array $recommendations
 *   An array of Recommendation objects keyed by nid.
 */
function hook_perls_recommendations_alter(array &$recommendations) {
  if (isset($recommendations['63'])) {
    unset($recommendations['63']);
  }
}

/**
 * @} End of "addtogroup hooks".
 */
