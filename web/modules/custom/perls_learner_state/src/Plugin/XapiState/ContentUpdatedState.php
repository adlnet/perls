<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define content updated state.
 *
 * @XapiState(
 *  id = "xapi_content_updated_state",
 *  label = @Translation("Xapi content updated state"),
 *  add_verb = @XapiVerb("update"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = NULL,
 * )
 */
class ContentUpdatedState extends XapiStateBase {}
