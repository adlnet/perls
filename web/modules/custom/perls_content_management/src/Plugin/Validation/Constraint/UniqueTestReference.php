<?php

namespace Drupal\perls_content_management\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Prevents that one test can belongs to more course.
 *
 * @Constraint(
 *   id = "UniqueTestReference",
 *   label = @Translation("Unique test relationship", context = "Validation"),
 *   type = { "entity", "entity_reference" }
 * )
 */
class UniqueTestReference extends Constraint {

  /**
   * Validator error message.
   *
   * @var string
   */
  public $message = 'You cannot add @entity_type_ref to more than one @entity_type.';

}
