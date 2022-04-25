<?php

namespace Drupal\perls_group_management\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueGroupTitle constraint.
 */
class UniqueGroupTitleValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($item, Constraint $constraint) {
    $value = $item->getValue()[0]['value'];
    $entity = $item->getEntity();

    if ($entity->getEntityTypeId() === 'group') {
      $entity_bundle = $entity->bundle();
      // Check if the unique title setting is enabled.
      $unique_entity_title_enabled = $entity->type->entity->getThirdPartySetting('perls_group_management', 'unique_group_title', 0);

      // Human-readable field name for group title.
      $unique_entity_title_label = \Drupal::config('core.base_field_override.group.' . $entity_bundle . '.label')->get('label') ?: 'Name';

      if ($unique_entity_title_enabled && $this->isNotUnique($value, $entity_bundle)) {
        $params = [
          '%label' => $unique_entity_title_label,
          '%value' => $value,
        ];
        $this->context->addViolation($constraint->notUnique, $params);
      }
    }
  }

  /**
   * Is Not unique?
   *
   * @param string $value
   *   Value of the field to check for uniqueness.
   * @param string $bundle
   *   Bundle of the entity.
   *
   * @return bool
   *   Whether the entity is unique or not
   */
  private function isNotUnique($value, $bundle) {
    // Find existing entity with the same group name.
    $query = \Drupal::entityQuery('group')
      ->condition('label', $value)
      ->condition('type', $bundle)
      ->range(0, 1);

    // Exclude the current entity.
    if (!empty($id = $this->context->getRoot()->getEntity()->id())) {
      $query->condition('id', $id, '!=');
    }
    $entities = $query->execute();
    if (!empty($entities)) {
      return TRUE;
    }
    return FALSE;
  }

}
