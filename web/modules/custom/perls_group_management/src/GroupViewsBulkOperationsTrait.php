<?php

namespace Drupal\perls_group_management;

use Drupal\group\Entity\Group;

/**
 * Trait to get the group from the current VBO context.
 */
trait GroupViewsBulkOperationsTrait {

  /**
   * Retrieves the current context data from the in-process VBO.
   *
   * VBO stores view information in a temp store which we can pull to determine
   * what the original arguments were of the view that triggered the operation.
   *
   * @return array
   *   The VBO context.
   */
  protected function getCurrentViewsBulkOperationContext() {
    $route_match = \Drupal::routeMatch();

    $view_id = $route_match->getParameter('view_id');
    $display_id = $route_match->getParameter('display_id');

    if (empty($view_id) || empty($display_id)) {
      return NULL;
    }

    $tempStore = \Drupal::service('tempstore.private')->get('views_bulk_operations_' . $view_id . '_' . $display_id);
    if (!$tempStore) {
      return NULL;
    }

    $data = $tempStore->get(\Drupal::currentUser()->id());
    return $data;
  }

  /**
   * Retrieves the current group from the VBO context.
   *
   * This currently assumes that the first argument passed
   * to the view is the group argument.
   *
   * @param array $context
   *   The context of the current VBO batch action (an array).
   *
   * @return \Drupal\group\Entity\Group|null
   *   Returns the current group, or null if the group could not be determined.
   */
  protected function getGroupFromViewsBulkOperationContext(array $context) {
    if (!is_array($context) || empty($context['arguments'])) {
      return NULL;
    }

    $group_id = $context['arguments'][0];
    $group = Group::load($group_id);

    return $group;
  }

}
