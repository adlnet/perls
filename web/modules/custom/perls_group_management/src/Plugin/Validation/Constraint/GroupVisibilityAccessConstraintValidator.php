<?php

namespace Drupal\perls_group_management\Plugin\Validation\Constraint;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Form API callback. Validate element value.
 */
class GroupVisibilityAccessConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    // Skip empty unique fields or arrays (aka #multiple).
    if (!$items || !$items instanceof EntityReferenceFieldItemList || !$items->referencedEntities()) {
      return;
    }
    /** @var \Drupal\user\UserInterface $user */
    $user = $items->getParent()->getEntity();

    // The groups submitted by the form.
    $submitted_group_ids = [];
    $submitted_groups = $items->referencedEntities();

    foreach ($submitted_groups as $group) {
      $submitted_group_ids[] = $group->id();
      $access = $group->access('join group');
      if ((!$access || $access instanceof AccessResultForbidden) && !$group->getMember($user)) {
        $this->context->addViolation($constraint->accessJoinDenied);
        return;
      }
    }

    // Ensure the user did not leave a private group.
    if (!isset($user->field_add_groups)) {
      return;
    }
    $user_groups = $user->field_add_groups->referencedEntities();

    foreach ($user_groups as $group) {
      $access = $group->access('leave group');
      if ((!$access || $access instanceof AccessResultForbidden) && !in_array($group->id(), $submitted_group_ids)) {
        $this->context->addViolation($constraint->accessLeaveDenied);
        return;
      }
    }

  }

}
