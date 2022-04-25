<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\user\UserInterface;
use Drupal\perls_learner_state\Plugin\XapiUserGoalBase;

/**
 * Define completed state.
 *
 * @XapiState(
 *  id = "xapi_define_goal",
 *  label = @Translation("Xapi state re-defined goals"),
 *  add_verb = @XapiVerb("defined"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = ""
 * )
 */
class UserDefineNewGoal extends XapiUserGoalBase {

  /**
   * The new goal value.
   *
   * @var int
   */
  protected $updatedValue;

  /**
   * {@inheritdoc}
   */
  protected function prepareStatement(int $timestamp = NULL, UserInterface $user = NULL) {
    parent::prepareStatement($timestamp, $user);
    $this->statement->setResultResponse($this->getNewGoalValue());
  }

  /**
   * Store the new update goal value.
   *
   * @param int $new_value
   *   The new goal value.
   */
  public function setNewGoalValue(int $new_value) {
    $this->updatedValue = $new_value;
  }

  /**
   * Gives back the updated goal value.
   *
   * @return int
   *   The updated goal value.
   */
  public function getNewGoalValue(): int {
    return $this->updatedValue;
  }

}
