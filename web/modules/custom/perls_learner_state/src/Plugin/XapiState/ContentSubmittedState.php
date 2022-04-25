<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define content submitted state.
 *
 * @XapiState(
 *  id = "xapi_content_submitted_state",
 *  label = @Translation("Xapi content submitted state"),
 *  add_verb = @XapiVerb("submit"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = NULL,
 * )
 */
class ContentSubmittedState extends XapiStateBase {}
