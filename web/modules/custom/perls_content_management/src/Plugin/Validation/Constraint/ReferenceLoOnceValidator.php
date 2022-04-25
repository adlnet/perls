<?php

namespace Drupal\perls_content_management\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * This class validates the reference field has only includes each lo once.
 */
class ReferenceLoOnceValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    if (empty($items)) {
      return;
    }

    $entity = $items->getEntity();
    $ids = [];
    foreach ($items->referencedEntities() as $ref_entity) {
      if (isset($ids[$ref_entity->id()])) {
        $this->context->addViolation($constraint->message, [
          '@entity_type_ref' => $ref_entity->label(),
          '@entity_type' => $entity->bundle(),
        ]);
      }
      $ids[$ref_entity->id()] = $ref_entity->id();
    }
  }

}
