<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define following state.
 *
 * @XapiState(
 *  id = "xapi_following_state",
 *  label = @Translation("Xapi following state"),
 *  add_verb = @XapiVerb("follow"),
 *  remove_verb = @XapiVerb("stopFollowing"),
 *  notifyOnXapi = TRUE,
 *  flag = "following"
 * )
 */
class FollowingState extends XapiStateBase {}
