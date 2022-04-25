<?php

namespace Drupal\entity_access_condition\Plugin\Condition;

use Drupal\Core\Entity\EntityInterface;

/**
 * Validates the current user can access the user from the current context.
 *
 * @Condition(
 *   id = "user_access",
 *   label = @Translation("User access"),
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user", label = @Translation("User"))
 *   }
 * )
 */
class UserAccess extends EntityAccessBase {

  /**
   * {@inheritdoc}
   */
  protected function getEntity(): ?EntityInterface {
    return $this->getContextValue('user');
  }

}
