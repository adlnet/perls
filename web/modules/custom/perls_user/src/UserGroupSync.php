<?php

namespace Drupal\perls_user;

use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupContent;
use Drupal\user\UserInterface;

/**
 * This helper function try to keep the group memberhsip and user group field.
 */
class UserGroupSync {

  const USER_ACCOUNT_GROUP_FIELD = 'field_add_groups';

  /**
   * Add group membership to group when a new user account has created.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   */
  public static function userAccountInsert(UserInterface $user) {
    self::addNewMemberShipToGroup($user);
  }

  /**
   * Update group membership when a user account has updated.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   */
  public static function userAccountUpdated(UserInterface $user) {
    self::addNewMemberShipToGroup($user);
    self::removeOldGroupReferences($user);
  }

  /**
   * Update the group reference field under user profile.
   *
   * @param \Drupal\group\Entity\GroupContent $group_content
   *   A group content entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function addNewGroupMembership(GroupContent $group_content) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $group_content->getEntity();
    /** @var \Drupal\group\Entity\Group $group */
    $group = $group_content->getGroup();

    $current_groups = array_column($user->{self::USER_ACCOUNT_GROUP_FIELD}->getValue(), 'target_id');
    if (!in_array($group->id(), $current_groups)) {
      $user->{self::USER_ACCOUNT_GROUP_FIELD}->appendItem($group);
      $user->save();
    }
  }

  /**
   * Update a user's group list after a membership delete.
   *
   * @param \Drupal\group\Entity\GroupContent $group_content
   *   A group content entity.
   */
  public static function deleteGroupMembership(GroupContent $group_content) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $group_content->getEntity();
    /** @var \Drupal\group\Entity\Group $group */
    $group = $group_content->getGroup();

    if (!$user) {
      return;
    }

    $gids = array_column($user->{self::USER_ACCOUNT_GROUP_FIELD}->getValue(), 'target_id');
    $index = array_search($group->id(), $gids);

    if ($index !== FALSE) {
      $user->{self::USER_ACCOUNT_GROUP_FIELD}->removeItem($index);
      $user->save();
    }
  }

  /**
   * Validates the user's selected groups on their profile.
   *
   * When a user does not have permissions to leave a group
   * it gets disabled on the form which stops it from being submitted
   * this function updates the submission to include all groups you are
   * not allowed to leave.
   *
   * @param array $element
   *   The form element where the user selected groups.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param object $complete_form
   *   The form.
   */
  public static function validateSelectedGroups(array &$element, FormStateInterface $form_state, &$complete_form) {
    $items = $form_state->getValue($element['#parents']) ?? [];
    $user = $form_state->getformObject()->getEntity();
    $selected_groups = array_column($items, 'target_id');

    foreach ($user->{UserGroupSync::USER_ACCOUNT_GROUP_FIELD}->referencedEntities() as $group) {
      if (!in_array($group->id(), $selected_groups) && !$group->access('leave group')) {
        $items[] = ['target_id' => $group->id()];
      }
    }

    $form_state->setValueForElement($element, $items);
  }

  /**
   * Sync the drupal field with group membership.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   */
  private static function addNewMemberShipToGroup(UserInterface $user) {
    if ($user->original !== NULL) {
      $original = array_column($user->original->{self::USER_ACCOUNT_GROUP_FIELD}->getValue(), 'target_id');
    }
    else {
      // If this is a new user account, there will be no original groups.
      $original = [];
    }

    $new = array_column($user->{self::USER_ACCOUNT_GROUP_FIELD}->getValue(), 'target_id');
    $new_gids = array_diff($new, $original);
    $groups = Group::loadMultiple($new_gids);

    foreach ($groups as $group) {
      if (!$group->access('join group') || $group->getMember($user)) {
        continue;
      }

      $group->addMember($user);
      self::invalidateGroupsTag([$group->id()]);
    }
  }

  /**
   * Delete the "extra" group memberships.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   */
  private static function removeOldGroupReferences(UserInterface $user) {
    $original = array_column($user->original->{self::USER_ACCOUNT_GROUP_FIELD}->getValue(), 'target_id');
    $new = array_column($user->{self::USER_ACCOUNT_GROUP_FIELD}->getValue(), 'target_id');
    $old_gids = array_diff($original, $new);
    $groups = Group::loadMultiple($old_gids);

    foreach ($groups as $group) {
      if (!$group->access('leave group')) {
        continue;
      }

      $group->removeMember($user);
      self::invalidateGroupsTag([$group->id()]);
    }
  }

  /**
   * Invalidate cache tag on set of groups.
   *
   * @param array $group_list
   *   The group id list.
   */
  private static function invalidateGroupsTag(array $group_list) {
    $cache_invalidator = \Drupal::service('cache_tags.invalidator');
    $tags = [];
    foreach ($group_list as $id) {
      $tags[] = 'group:' . $id;
    }

    if (!empty($tags)) {
      $cache_invalidator->invalidateTags($tags);
    }
  }

}
