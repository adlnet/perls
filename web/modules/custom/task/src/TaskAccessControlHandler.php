<?php

namespace Drupal\task;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Access controller for the task entity.
 *
 * @see \Drupal\task\Entity\Task.
 */
class TaskAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\task\Entity\TaskInterface $entity */
    $type = $entity->bundle();

    switch ($operation) {
      case 'view':
        $permission = $this->checkOwn($entity, $operation, $account);
        if (!empty($permission)) {
          return AccessResult::allowed();
        }
        return AccessResult::allowedIfHasPermission($account, 'view any task entities');

      case 'update':
        $permission = $this->checkOwn($entity, $operation, $account);
        if (!empty($permission)) {
          return AccessResult::allowed();
        }
        return AccessResult::allowedIfHasPermission($account, "edit any $type task");

      case 'delete':
        $permission = $this->checkOwn($entity, $operation, $account);
        if (!empty($permission)) {
          return AccessResult::allowed();
        }
        return AccessResult::allowedIfHasPermission($account, "delete any $type task");
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, "create $entity_bundle task");
  }

  /**
   * Test for given 'own' permission.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object.
   * @param string $operation
   *   Operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   *
   * @return string|null
   *   The permission string indicating it's allowed.
   */
  protected function checkOwn(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\task\Entity\Task $entity */
    $uid = $entity->getOwnerId();

    $is_own = $account->isAuthenticated() && $account->id() == $uid;
    if (!$is_own) {
      return;
    }

    $bundle = $entity->bundle();

    $ops = [
      'create' => 'create %bundle task',
      'view' => 'view own %bundle task',
      'update' => 'edit own %bundle task',
      'delete' => 'delete own %bundle task',
    ];
    $permission = strtr($ops[$operation], ['%bundle' => $bundle]);

    if ($account->hasPermission($permission)) {
      return $permission;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    if (!isset($items)) {
      return parent::checkFieldAccess($operation, $field_definition, $account, $items);
    }
    // Only users with the administer nodes permission can edit administrative
    // fields.
    $administrative_fields = ['user_id'];
    $type = $items->getEntity()->type->entity->id();
    if ($operation == 'edit' && in_array($field_definition->getName(), $administrative_fields, TRUE)) {
      return AccessResult::allowedIfHasPermission($account, "edit any $type task");
    }
    return parent::checkFieldAccess($operation, $field_definition, $account, $items);
  }

}
