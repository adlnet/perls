<?php

/**
 * @file
 * Hooks for xAPI reporting.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Perform alterations on an xAPI template statements before it is used.
 *
 * XAPI template statements are provided to the client interface for
 * sending xAPI statements.
 *
 * @param \Drupal\xapi\XapiStatement $statement
 *   The statement.
 * @param array $context
 *   Context for the statement.
 *
 * @see \Drupal\xapi\XapiStatement
 */
function hook_xapi_statement_template_alter(\Drupal\xapi\XapiStatement $statement, array &$context) {
  $entity = $context['entity'];
  $statement->addParentContext($entity->get('field_parent')->entity);
}

/**
 * @} End of "addtogroup hooks".
 */
