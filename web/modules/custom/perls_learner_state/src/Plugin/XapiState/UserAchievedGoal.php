<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\XapiUserGoalBase;

/**
 * Define completed state.
 *
 * @XapiState(
 *  id = "xapi_user_achieved_goal",
 *  label = @Translation("Xapi state user acheived goal"),
 *  add_verb = @XapiVerb("completed"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = ""
 * )
 */
class UserAchievedGoal extends XapiUserGoalBase {}
