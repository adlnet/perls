<?php

namespace Drupal\perls_group_management\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if user has access to group.
 *
 * @Constraint(
 *   id = "GroupVisibilityAccessConstraint",
 *   label = @Translation("Group Visibility Access", context = "Validation"),
 * )
 */
class GroupVisibilityAccessConstraint extends Constraint {

  /**
   * The message that will be shown if the user cannot join group is incorrect.
   *
   * @var accessJoinDenied
   */
  public $accessJoinDenied = 'You do not have access to join a group';

  /**
   * The message that will be shown if the user cannot leave group is incorrect.
   *
   * @var accessLeaveDenied
   */
  public $accessLeaveDenied = 'You do not have access to leave a group';

}
