<?php

namespace Drupal\task\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Count constraint.
 *
 * Overrides the symfony constraint to use Drupal-style replacement patterns.
 *
 * @Constraint(
 *   id = "TaskCountConstraint",
 *   label = @Translation("Count", context = "Validation"),
 *   type = "entity:task"
 * )
 */
class TaskCountConstraint extends Constraint {

  /**
   * This is the constraint message.
   *
   * @var string
   */
  public $maxMessage = 'This collection should contain 10 element or less.|This collection should contain 10 elements or less.';

  /**
   * {@inheritdoc}
   */
  public function validatedBy() {
    return 'Drupal\task\Plugin\Validation\Constraint\TaskCountValidator';
  }

}
