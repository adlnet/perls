<?php

namespace Drupal\perls_content_management\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the SchedulerUnPublishAfter constraint.
 */
class SchedulerUnPublishAfterConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    $unpublish_on = $entity->value;

    $request_time = \Drupal::time()->getRequestTime();
    if ($unpublish_on && ($unpublish_on < strtotime("+30 minutes", $request_time))) {
      $this->context->buildViolation($constraint->messageUnPublishOnDateAfter)
        ->atPath('unpublish_on')
        ->addViolation();
    }
  }

}
