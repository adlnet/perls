<?php

namespace Drupal\perls_content_management\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the SchedulerPublishAfter constraint.
 */
class SchedulerPublishAfterConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    $publish_on = $entity->value;

    $request_time = \Drupal::time()->getRequestTime();
    if ($publish_on && ($publish_on < strtotime("+30 minutes", $request_time))) {
      $this->context->buildViolation($constraint->messagePublishOnDateAfter)
        ->atPath('publish_on')
        ->addViolation();
    }
  }

}
