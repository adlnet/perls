<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\perls_learner_state\Plugin\QuizStateBase;

/**
 * Define quiz asked state.
 *
 * @XapiState(
 *  id = "xapi_quiz_asked_state",
 *  label = @Translation("Xapi quiz question shown state"),
 *  add_verb = @XapiVerb("asked"),
 *  remove_verb = NULL,
 *  notifyOnXapi = TRUE,
 *  flag = ""
 * )
 */
class QuizAskedState extends QuizStateBase {

  /**
   * {@inheritDoc}
   */
  protected function prepareStatement(int $timestamp = NULL, UserInterface $user = NULL) {
    parent::prepareStatement($timestamp, User::getAnonymousUser());
  }

}
