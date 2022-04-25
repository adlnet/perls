<?php

namespace Drupal\perls_podcast\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is not a future date.
 *
 * @Constraint(
 *   id = "perls_future_date",
 *   label = @Translation("Perls Future Date", context = "Validation"),
 *   type = "date"
 * )
 */
class PerlsFutureDate extends Constraint {
  /**
   * The message that will be shown if the date is future date.
   *
   * @var string
   */
  public $futureDate = '%value is a future date.';

}
