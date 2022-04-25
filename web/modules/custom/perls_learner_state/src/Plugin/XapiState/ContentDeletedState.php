<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define content updated state.
 *
 * @XapiState(
 *  id = "xapi_content_deleted_state",
 *  label = @Translation("Xapi content deleted state"),
 *  add_verb = @XapiVerb("delete"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = NULL,
 * )
 */
class ContentDeletedState extends XapiStateBase {}
