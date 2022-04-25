<?php

namespace Drupal\notifications_ui_additions\Plugin\EntityReferenceSelection;

use Drupal\node\Plugin\EntityReferenceSelection\NodeSelection;

/**
 * Provides specific access control for the node entity type.
 *
 * @EntityReferenceSelection(
 *   id = "default:published_node",
 *   label = @Translation("Node by status selection"),
 *   entity_types = {"node"},
 *   group = "push_notifications",
 *   weight = 3
 * )
 */
class NodeByStatusSelection extends NodeSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    // Show only published content.
    $query->condition('status', 1, '=');
    return $query;
  }

}
