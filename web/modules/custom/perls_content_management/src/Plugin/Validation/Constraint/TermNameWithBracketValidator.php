<?php

namespace Drupal\perls_content_management\Plugin\Validation\Constraint;

use Drupal\taxonomy\Entity\Term;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validator TermNameWithBracket constraint.
 */
class TermNameWithBracketValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if ($value instanceof Term && preg_match('/(?<=\()(.+)(?=\))/is', $value->label())) {
      $this->context->addViolation($constraint->message);
    }
  }

}
