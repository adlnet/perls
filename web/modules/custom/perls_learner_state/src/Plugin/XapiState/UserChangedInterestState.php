<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define xapi_add_user_interest state.
 *
 * When a user has add a new interest to own set.
 *
 * @XapiState(
 *  id = "xapi_changed_user_interest",
 *  label = @Translation("A user add new ineterest"),
 *  add_verb = @XapiVerb("added"),
 *  remove_verb = @XapiVerb("removed"),
 *  notifyOnXapi = FALSE,
 *  flag = ""
 * )
 */
class UserChangedInterestState extends XapiStateBase {}
