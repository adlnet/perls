<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define launched state.
 *
 * @XapiState(
 *  id = "xapi_seen_state",
 *  label = @Translation("Xapi seen state"),
 *  add_verb = @XapiVerb("launched"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = ""
 * )
 */
class SeenState extends XapiStateBase {}
