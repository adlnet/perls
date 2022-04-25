<?php

namespace Drupal\perls_content_management;

use Drupal\node\Entity\Node;

/**
 * This class help to discover the relationship between nodes.
 */
class EntityReferenceHelper {

  /**
   * Laod all nodes where a specific node appears as reference.
   *
   * @param \Drupal\node\Entity\Node $node
   *   A drupal entity.
   *
   * @return mixed
   *   Gives back the parent node otherwise NULL.
   */
  public function getTestParentCourse(Node $node) {
    // Currently a test only can belongs to one node.
    $node = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('field_learning_content.entity:node.nid', $node->id(), '=')
      ->execute();

    if (!empty($node)) {
      $nid = reset($node);
      return Node::load($nid);
    }
    else {
      return NULL;
    }
  }

}
