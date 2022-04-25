<?php

namespace Drupal\perls_content_management\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that term name doesn't contain any brackets.
 *
 * @Constraint(
 *   id = "TermNameWithBracket",
 *   label = @Translation("Term validator: Bracket", context = "Validation"),
 *   type = "string"
 * )
 */
class TermNameWithBracket extends Constraint {

  /**
   * Validator error message.
   *
   * @var string
   */
  public $message = "Parenthesis characters '( )' are not allowed in term names.";

}
