<?php

namespace Drupal\perls_content_management\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;

/**
 * Validates publish on values.
 *
 * @Constraint(
 *   id = "SchedulerPublishAfter",
 *   label = @Translation("Scheduler publish after 30 minutes", context = "Validation"),
 *   type = "entity:node"
 * )
 */
class SchedulerPublishAfterConstraint extends CompositeConstraintBase {

  /**
   * Message shown when publish_on is not the future.
   *
   * @var string
   */
  public $messagePublishOnDateAfter = "The 'Publish on' date should be set to at least 30 minutes in the future.";

  /**
   * {@inheritdoc}
   */
  public function coversFields() {
    return ['publish_on'];
  }

}
