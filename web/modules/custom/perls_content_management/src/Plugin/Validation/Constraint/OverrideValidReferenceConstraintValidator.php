<?php

namespace Drupal\perls_content_management\Plugin\Validation\Constraint;

use Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface;
use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraintValidator;
use Drupal\Core\Validation\Plugin\Validation\Constraint\LengthConstraint;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\Validator\Constraint;

/**
 * Overrides the ValidReferenceConstraintValidator that we bypass some case.
 */
class OverrideValidReferenceConstraintValidator extends ValidReferenceConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\Core\Field\FieldItemListInterface $value */
    if (!isset($value)) {
      return;
    }

    // Collect new entities and IDs of existing entities across the field items.
    $new_entities = [];
    $target_ids = [];
    foreach ($value as $delta => $item) {
      $target_id = $item->target_id;
      // We don't use a regular NotNull constraint for the target_id property as
      // NULL is allowed if the entity property contains an unsaved entity.
      // @see \Drupal\Core\TypedData\DataReferenceTargetDefinition::getConstraints()
      if (!$item->isEmpty() && $target_id === NULL) {
        if (!$item->entity->isNew()) {
          $this->context->buildViolation($constraint->nullMessage)
            ->atPath((string) $delta)
            ->addViolation();
          return;
        }
        $new_entities[$delta] = $item->entity;
      }

      // '0' or NULL are considered valid empty references.
      if (!empty($target_id)) {
        $target_ids[$delta] = $target_id;
      }
    }

    // Early opt-out if nothing to validate.
    if (!$new_entities && !$target_ids) {
      return;
    }

    $entity = !empty($value->getParent()) ? $value->getEntity() : NULL;

    /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler * */
    $handler = $this->selectionManager->getSelectionHandler($value->getFieldDefinition(), $entity);
    $target_type_id = $value->getFieldDefinition()->getSetting('target_type');

    // Add violations on deltas with a new entity that is not valid.
    if ($new_entities) {
      foreach ($new_entities as $new_entity) {
        if ($new_entity instanceof Term) {
          $referenced_entity_type = $new_entity->getEntityType();
          if ($referenced_entity_type->hasKey('label')) {
            $label_name = $referenced_entity_type->getKey('label');
            $violations = $new_entity->validate()->getByField($label_name);
            $nameLength = !empty($new_entity->getName()) ? strlen($new_entity->getName()) : '';
            foreach ($violations as $violation) {
              $constraints = $violation->getConstraint();
              if ($constraints instanceof LengthConstraint) {
                $characterLimit = $violation->getPlural();
                $errorMessage = t('@Label cannot be longer than @characterLimit characters but is currently @nameLength characters long.',
                  [
                    '@Label' => $value->getFieldDefinition()->getLabel(),
                    '@characterLimit' => $characterLimit,
                    '@nameLength' => $nameLength,
                  ]);
                $this->context->addViolation($errorMessage);
              }
              else {
                $this->context->addViolation($violation->getMessageTemplate());
              }
            }
          }
        }
      }
      if ($handler instanceof SelectionWithAutocreateInterface) {
        $valid_new_entities = $handler->validateReferenceableNewEntities($new_entities);
        $invalid_new_entities = array_diff_key($new_entities, $valid_new_entities);
      }
      else {
        // If the selection handler does not support referencing newly created
        // entities, all of them should be invalidated.
        $invalid_new_entities = $new_entities;
      }

      foreach ($invalid_new_entities as $delta => $entity) {
        $this->context->buildViolation($constraint->invalidAutocreateMessage)
          ->setParameter('%type', $target_type_id)
          ->setParameter('%label', $entity->label())
          ->atPath((string) $delta . '.entity')
          ->setInvalidValue($entity)
          ->addViolation();
      }
    }

    // Add violations on deltas with a target_id that is not valid.
    if ($target_ids) {
      // Get a list of pre-existing references.
      $previously_referenced_ids = [];
      if ($value->getParent() && ($entity = $value->getEntity()) && !$entity->isNew()) {
        $existing_entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id());
        foreach ($existing_entity->{$value->getFieldDefinition()->getName()}->getValue() as $item) {
          $previously_referenced_ids[$item['target_id']] = $item['target_id'];
        }
      }

      // We have an user reference field but if we block this user we cannot
      // change the referred field.
      $valid_target_ids = [];
      if ($target_type_id === 'user' && !empty($previously_referenced_ids)) {
        $referenced_entities = $this->entityTypeManager->getStorage($target_type_id)
          ->loadMultiple($previously_referenced_ids);
        foreach ($previously_referenced_ids as $uid) {
          /** @var \Drupal\user\Entity\User $referred_user */
          $referred_user = $referenced_entities[$uid] ?? NULL;
          if ($referred_user && $referred_user->isBlocked()) {
            $valid_target_ids[] = $uid;
          }
        }
      }

      $valid_target_ids = array_merge($valid_target_ids, $handler->validateReferenceableEntities($target_ids));

      if ($invalid_target_ids = array_diff($target_ids, $valid_target_ids)) {
        // For accuracy of the error message, differentiate non-referenceable
        // and non-existent entities.
        $existing_entities = $this->entityTypeManager->getStorage($target_type_id)->loadMultiple($invalid_target_ids);
        foreach ($invalid_target_ids as $delta => $target_id) {
          // Check if any of the invalid existing references are simply not
          // accessible by the user, in which case they need to be excluded from
          // validation.
          if (isset($previously_referenced_ids[$target_id]) && isset($existing_entities[$target_id]) && !$existing_entities[$target_id]->access('view')) {
            continue;
          }

          $message = isset($existing_entities[$target_id]) ? $constraint->message : $constraint->nonExistingMessage;
          $this->context->buildViolation($message)
            ->setParameter('%type', $target_type_id)
            ->setParameter('%id', $target_id)
            ->atPath((string) $delta . '.target_id')
            ->setInvalidValue($target_id)
            ->addViolation();
        }
      }
    }
  }

}
