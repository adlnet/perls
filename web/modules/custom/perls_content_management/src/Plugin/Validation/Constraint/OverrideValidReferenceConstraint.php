<?php

namespace Drupal\perls_content_management\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint;

/**
 * Overrides the ValidReferenceConstraint that we bypass some edge case.
 */
class OverrideValidReferenceConstraint extends ValidReferenceConstraint {}
