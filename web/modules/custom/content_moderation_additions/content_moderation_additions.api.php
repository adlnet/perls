<?php

/**
 * @file
 * Hooks and documentation related to Moderation Additions module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alters the user query for selecting users elgible to review an entity.
 *
 * @param QueryInterface &$query
 *   The query interface to alter.
 * @param array $context
 *   An associative array containing the following key-value pairs:
 *   - entity: The entity being moderated.
 */
function hook_content_moderation_additions_reviewers_query_alter(QueryInterface &$query, array $context) {
  $query->condition('roles', 'administrator');
}

/**
 * Alters the list of users elifible to review an entity.
 *
 * @param array &$uids
 *   An array of user ids eligible to review the entity.
 * @param array $context
 *   An associative array containing the following key-value pairs:
 *   - entity: The entity being moderated.
 */
function hook_content_moderation_additions_reviewers_alter(array &$uids, array $context) {
  $uids[] = 1;
}

/**
 * @} End of "addtogroup hooks".
 */
