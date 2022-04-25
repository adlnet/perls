<?php

namespace Drupal\perls_group_management\Plugin\Action;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Session\AccountInterface;

/**
 * Adds user to a group.
 *
 * @Action(
 *   id = "perls_group_management_add_user",
 *   label = @Translation("Add user to group"),
 *   type = "user",
 * )
 */
class AddUserToGroup extends AddContentToGroup {

  /**
   * {@inheritdoc}
   */
  public function execute($user = NULL) {
    $groups = $this->getGroups();

    foreach ($groups as $group) {
      try {
        $group->addMember($user);
        $this->messenger()->addMessage($this->t('Added %user to %group', [
          '%user' => $user->label(),
          '%group' => $group->label(),
        ]));
      }
      catch (EntityStorageException $e) {
        // Content was already in group; nothing to do.
      }
    }

    if (!empty($groups)) {
      return $this->t('Added to group');
    }

    return $this->t('No groups to update');
  }

  /**
   * {@inheritdoc}
   */
  public function access($user, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $groups = $this->getGroups();
    if (empty($groups)) {
      return FALSE;
    }

    $permission = 'administer members';
    foreach ($groups as $group) {
      if (!$group->hasPermission($permission, $account)) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
