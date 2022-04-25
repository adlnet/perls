<?php

namespace Drupal\task;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\task\Entity\TaskInterface;

/**
 * Defines the storage handler class for task entities.
 *
 * This extends the base storage class, adding required special handling for
 * task entities.
 *
 * @ingroup task
 */
class TaskStorage extends SqlContentEntityStorage implements TaskStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(TaskInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {task_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {task_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(TaskInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {task_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('task_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
