<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define interacted state.
 *
 * @XapiState(
 *  id = "xapi_interacted_state",
 *  label = @Translation("Xapi interacted state"),
 *  add_verb = @XapiVerb("interacted"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = ""
 * )
 */
class InteractedState extends XapiStateBase {}
