<?php

namespace Drupal\task;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\task\Entity\TaskType;

/**
 * Provides dynamic permissions for task of different types.
 *
 * @ingroup task
 */
class TaskPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of node type permissions.
   *
   * @return array
   *   The Task by bundle permissions.
   *   @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function generatePermissions(): array {
    $perms = [];

    foreach (TaskType::loadMultiple() as $type) {
      $perms += $this->buildPermissions($type);
    }

    return $perms;
  }

  /**
   * Returns a list of node permissions for a given node type.
   *
   * @param \Drupal\task\Entity\TaskType $type
   *   The Task type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(TaskType $type): array {
    $type_id = $type->id();
    $type_params = ['%type_name' => $type->label()];

    return [
      "create $type_id task" => [
        'title' => $this->t('%type_name: Create new task', $type_params),
      ],
      "edit own $type_id task" => [
        'title' => $this->t('%type_name: Edit own task', $type_params),
      ],
      "edit any $type_id task" => [
        'title' => $this->t('%type_name: Edit any task', $type_params),
      ],
      "delete own $type_id task" => [
        'title' => $this->t('%type_name: Delete own task', $type_params),
      ],
      "delete any $type_id task" => [
        'title' => $this->t('%type_name: Delete any task', $type_params),
      ],
      "view own $type_id task" => [
        'title' => $this->t('%type_name: View own task', $type_params),
      ],
      "view any $type_id task" => [
        'title' => $this->t('%type_name: View any task', $type_params),
      ],
      "view $type_id revisions" => [
        'title' => $this->t('%type_name: View revisions', $type_params),
        'description' => t('To view a revision, you also need permission to view the task item.'),
      ],
      "revert $type_id revisions" => [
        'title' => $this->t('%type_name: Revert revisions', $type_params),
        'description' => t('To revert a revision, you also need permission to edit the task item.'),
      ],
      "delete $type_id revisions" => [
        'title' => $this->t('%type_name: Delete revisions', $type_params),
        'description' => $this->t('To delete a revision, you also need permission to delete the task item.'),
      ],
    ];
  }

}
