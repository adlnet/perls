<?php

/**
 * @file
 * Contains task.page.inc.
 *
 * Page callback for task entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for task templates.
 *
 * Default template: task.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_task(array &$variables) {
  // Fetch Task Entity Object.
  $task = $variables['elements']['#task'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
