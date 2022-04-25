<?php

namespace Drupal\perls_goals\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event triggered when a user achieved the own goal.
 */
class AchievedGoalEvent extends Event {

  const ACHIEVED_GOAL = 'perls_goals.achieved_goal';

  /**
   * Store the name of drupal field which contained the goal.
   *
   * @var string
   */
  private $goalField = NULL;

  /**
   * The user will own this log message.
   *
   * @var int
   */
  private $uid = 0;

  /**
   * Gets the name of drupal field which contains the goal.
   *
   * @return string
   *   The field config.
   */
  public function getGoalField(): string {
    return $this->goalField;
  }

  /**
   * Sets the name of goal(api name)
   *
   * @param string $goal_field
   *   The field config.
   */
  public function setGoalField($goal_field) {
    $this->goalField = $goal_field;
  }

  /**
   * Gives back the uid.
   *
   * @return int
   *   The user id who will own this log message.
   */
  public function getUid(): int {
    return $this->uid;
  }

  /**
   * Set the uid property.
   *
   * @param int $uid
   *   An existing drupal user id.
   */
  public function setUid(int $uid) {
    $this->uid = $uid;
  }

}
