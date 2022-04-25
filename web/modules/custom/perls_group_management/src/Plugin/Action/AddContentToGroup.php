<?php

namespace Drupal\perls_group_management\Plugin\Action;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Session\AccountInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\perls_group_management\GroupViewsBulkOperationsTrait;

/**
 * Adds a content item to a group.
 *
 * @Action(
 *   id = "perls_group_management_add_content",
 *   label = @Translation("Add to group"),
 *   type = "node",
 * )
 */
class AddContentToGroup extends ViewsBulkOperationsActionBase {
  use GroupViewsBulkOperationsTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($node = NULL) {
    $groups = $this->getGroups();

    foreach ($groups as $group) {
      try {
        $group->addContent($node, 'group_node:' . $node->getType());
        $this->messenger()->addMessage($this->t('Added %node to %group', [
          '%node' => $node->label(),
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
  public function access($node, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $groups = $this->getGroups();
    if (empty($groups)) {
      return FALSE;
    }

    $permission = 'create group_node:' . $node->getType() . ' content';
    foreach ($groups as $group) {
      if (!$group->hasPermission($permission, $account)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Retrieves the groups where the content should be added.
   *
   * @return \Drupal\group\Entity\Group[]
   *   The groups where the content should be added.
   */
  protected function getGroups() {
    $group = $this->getGroupFromViewsBulkOperationContext($this->context);
    if ($group) {
      return [$group];
    }

    // @todo Enable the user to select groups.
    return [];
  }

}
