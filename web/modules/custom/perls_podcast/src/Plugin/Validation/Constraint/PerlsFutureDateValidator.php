<?php

namespace Drupal\perls_podcast\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the PerlsFutureDate constraint.
 */
class PerlsFutureDateValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    $now = time();
    foreach ($items as $item) {
      if (strtotime($item->value) > $now) {
        $this->context->addViolation($constraint->futureDate, ['%value' => $item->value]);
      }
    }
  }

}
