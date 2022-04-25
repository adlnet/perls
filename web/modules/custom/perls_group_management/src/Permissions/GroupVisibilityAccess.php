<?php

namespace Drupal\perls_group_management\Permissions;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Determines access for group based on group's visibility and user.
 */
class GroupVisibilityAccess implements AccessInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $membershipLoader;

  /**
   * Constructs a GroupCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\group\GroupMembershipLoaderInterface $membershipLoader
   *   The group membership manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GroupMembershipLoaderInterface $membershipLoader) {
    $this->entityTypeManager = $entity_type_manager;
    $this->membershipLoader = $membershipLoader;
  }

  /**
   * Checks access for group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The currently logged in user.
   * @param string $operation
   *   The type of operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(GroupInterface $group, $operation, AccountInterface $account) {
    $visibility = $group->field_visibility->value || 0;
    switch ($visibility) {
      case GroupVisibilityPermission::PUBLIC_GROUP:
        switch ($operation) {
          case 'join group':
            return AccessResult::allowed();

          case 'leave group':
            return AccessResult::allowed();

          case 'view':
          case 'view group':
            return AccessResult::allowed();

        }
        break;

      case GroupVisibilityPermission::PRIVATE_GROUP:
        switch ($operation) {
          case 'join group':
            return $this->canManagePrivateGroup($account)
              ? AccessResult::allowed() : AccessResult::forbidden();

          case 'leave group':
            return $this->canManagePrivateGroup($account)
              ? AccessResult::allowed() : AccessResult::forbidden();

          case 'view':
          case 'view group':
            return $this->membershipLoader->load($group, $account) || $this->canManagePrivateGroup($account)
              ? AccessResult::allowed() : AccessResult::forbidden();

          default:
            return $this->canManagePrivateGroup($account) ? AccessResult::neutral() : AccessResult::forbidden();
        }
        break;

    }

    return AccessResult::neutral();
  }

  /**
   * Checks if user can manage a private group.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in user.
   *
   * @return bool
   *   True if user can manage a private group.
   */
  private function canManagePrivateGroup(AccountInterface $account) {
    // This should be limited to admins and
    // people who can access and create new groups.
    return $account->hasPermission('administer group') ||
      ($account->hasPermission('access group overview') && $account->hasPermission('create audience group'));
  }

}
