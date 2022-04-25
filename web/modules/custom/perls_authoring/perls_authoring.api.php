<?php

/**
 * @file
 * Hooks and documentation related to Perls Authoring module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the callout type options provided in the callout authoring type.
 *
 * @param array &$options
 *   An associative array with key-value pairs of callout types and labels.
 * @param array $context
 *   An associative array containing the following key-value pairs:
 *   - entity: The entity where the callout is being added.
 */
function hook_perls_authoring_callout_types_alter(array &$options, array $context) {
  $options['value'] = t('Label');
}

/**
 * @} End of "addtogroup hooks".
 */
