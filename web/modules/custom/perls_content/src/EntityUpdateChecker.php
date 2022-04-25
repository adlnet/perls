<?php

namespace Drupal\perls_content;

use Drupal\Core\Entity\EntityInterface;

/**
 * Service class to inspect the intity object to see if it has been modified.
 */
class EntityUpdateChecker {

  /**
   * Compares the entity with its original copy to check if it was modified.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to be compared.
   * @param string[] $field_names
   *   Ensures at least one of the field names in the list has changed.
   *
   * @return bool
   *   TRUE if entity altered, FALSE otherwise.
   */
  public function isAltered(EntityInterface $entity, array $field_names = []): bool {
    $original = $entity->original;
    foreach ($entity as $field_name => $field_value) {
      if (!empty($field_names) && !in_array($field_name, $field_names)) {
        continue;
      }
      $original_field = $original->get($field_name);
      if (!$field_value->equals($original_field)) {
        // Return even if one of the field is modified.
        return TRUE;
      }
    }

    return FALSE;
  }

}
