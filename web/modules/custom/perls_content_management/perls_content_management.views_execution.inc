<?php

/**
 * @file
 * Provide views runtime hooks.
 */

use Drupal\views\ViewExecutable;

/**
 * Implements hook_views_query_substitutions().
 *
 * Allow replacement of user ID from url arg.
 */
function perls_content_management_views_query_substitutions(ViewExecutable $view) {
  return ['***PERLS_USER_URL_ARG***' => !empty($view->args) ? $view->args[0] : NULL];
}
