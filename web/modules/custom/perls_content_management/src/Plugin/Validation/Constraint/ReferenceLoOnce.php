<?php

namespace Drupal\perls_content_management\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Prevents content from being added to a course more than once.
 *
 * @Constraint(
 *   id = "ReferenceLoOnce",
 *   label = @Translation("Add Learning object once", context = "Validation"),
 *   type = { "entity", "entity_reference" }
 * )
 */
class ReferenceLoOnce extends Constraint {

  /**
   * Validator error message.
   *
   * @var string
   */
  public $message = 'You cannot add @entity_type_ref to a @entity_type more than once.';

}
