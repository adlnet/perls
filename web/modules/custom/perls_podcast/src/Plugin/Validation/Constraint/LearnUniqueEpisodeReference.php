<?php

namespace Drupal\perls_podcast\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Prevents that one episode can belong to more podcast.
 *
 * @Constraint(
 *   id = "LearnUniqueEpisodeReference",
 *   label = @Translation("Unique episode relationship", context = "Validation"),
 *   type = { "entity", "entity_reference" }
 * )
 */
class LearnUniqueEpisodeReference extends Constraint {
  /**
   * Validator error message.
   *
   * @var string
   */
  public $message = 'You cannot add @entity_type_ref to more than one @entity_type.';

}
