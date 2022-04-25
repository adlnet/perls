<?php

namespace Drupal\perls_group_management\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is a unique integer.
 *
 * @Constraint(
 *   id = "UniqueGroupTitle",
 *   label = @Translation("Unique Group Title", context = "Validation"),
 *   type = "string"
 * )
 */
class UniqueGroupTitle extends Constraint {
  /**
   * The message that will be shown if the value is not unique.
   *
   * @var string
   */
  public $notUnique = '%label "%value" is already in use. It must be unique.';

}
