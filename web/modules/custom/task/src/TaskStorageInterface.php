<?php

namespace Drupal\task;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface TaskStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of task revision IDs for a specific task.
   *
   * @param \Drupal\task\Entity\TaskInterface $entity
   *   The task entity.
   *
   * @return int[]
   *   task revision IDs (in ascending order).
   */
  public function revisionIds(TaskInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as task author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   task revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\task\Entity\TaskInterface $entity
   *   The task entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(TaskInterface $entity);

  /**
   * Unsets the language for all task with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
