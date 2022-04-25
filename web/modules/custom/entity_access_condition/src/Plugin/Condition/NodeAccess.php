<?php

namespace Drupal\entity_access_condition\Plugin\Condition;

use Drupal\Core\Entity\EntityInterface;

/**
 * Validates the current user can access the node from the current context.
 *
 * @Condition(
 *   id = "node_access",
 *   label = @Translation("Node access"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
class NodeAccess extends EntityAccessBase {

  /**
   * {@inheritdoc}
   */
  protected function getEntity(): ?EntityInterface {
    return $this->getContextValue('node');
  }

}
