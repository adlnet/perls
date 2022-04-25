<?php

namespace Drupal\content_moderation_additions\Plugin\Condition;

use Drupal\rules\Core\RulesConditionBase;
use Drupal\user\UserInterface;

/**
 * Provides a 'User has id' condition.
 *
 * @Condition(
 *   id = "rules_user_has_id",
 *   label = @Translation("User has ID"),
 *   category = @Translation("User"),
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user",
 *       label = @Translation("User"),
 *       description = @Translation("Specifies the user account to check."),
 *     ),
 *     "id" = @ContextDefinition("string",
 *       label = @Translation("ID"),
 *       description = @Translation("Specifies the User IDs to check for."),
 *       multiple = TRUE,
 *     ),
 *   }
 * )
 */
class UserHasId extends RulesConditionBase {

  /**
   * Evaluate if user has id.
   *
   * @param \Drupal\user\UserInterface $user
   *   The account to check.
   * @param int[] $ids
   *   Array of user IDs.
   *
   * @return bool
   *   TRUE if the user has the id.
   */
  protected function doEvaluate(UserInterface $user, array $ids) {
    return in_array($user->id(), $ids);
  }

}
