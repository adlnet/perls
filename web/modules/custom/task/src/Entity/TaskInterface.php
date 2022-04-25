<?php

namespace Drupal\task\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining task entities.
 *
 * @ingroup task
 */
interface TaskInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the task name.
   *
   * @return string
   *   Name of the task.
   */
  public function getName();

  /**
   * Sets the task name.
   *
   * @param string $name
   *   The task name.
   *
   * @return \Drupal\task\Entity\TaskInterface
   *   The called task entity.
   */
  public function setName($name);

  /**
   * Gets the task creation timestamp.
   *
   * @return int
   *   Creation timestamp of the task.
   */
  public function getCreatedTime();

  /**
   * Sets the task creation timestamp.
   *
   * @param int $timestamp
   *   The task creation timestamp.
   *
   * @return \Drupal\task\Entity\TaskInterface
   *   The called task entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the task revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the task revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\task\Entity\TaskInterface
   *   The called task entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the task revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the task revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\task\Entity\TaskInterface
   *   The called task entity.
   */
  public function setRevisionUserId($uid);

  /**
   * Gets the weight property of task.
   */
  public function getWeight();

  /**
   * Set the task's weight.
   *
   * @param int $weight
   *   The weight.
   */
  public function setWeight(int $weight);

  /**
   * Set the completion_date property.
   *
   * @param string $completionDate
   *   Date of completion.
   */
  public function setCompletionDate($completionDate);

  /**
   * Gets the completion_date property.
   */
  public function getCompletionDate();

}
