<?php

namespace Drupal\perls_content_management\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;

/**
 * Validates publish on values.
 *
 * @Constraint(
 *   id = "SchedulerUnPublishAfter",
 *   label = @Translation("Scheduler unpublish after 30 minutes", context = "Validation"),
 *   type = "entity:node"
 * )
 */
class SchedulerUnPublishAfterConstraint extends CompositeConstraintBase {

  /**
   * Message shown when unpublish_on is not the future.
   *
   * @var string
   */
  public $messageUnPublishOnDateAfter = "The 'Unpublish on' date should be set to at least 30 minutes in the future.";

  /**
   * {@inheritdoc}
   */
  public function coversFields() {
    return ['unpublish_on'];
  }

}
