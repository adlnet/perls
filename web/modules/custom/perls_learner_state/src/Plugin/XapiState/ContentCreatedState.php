<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define content created state.
 *
 * @XapiState(
 *  id = "xapi_content_created_state",
 *  label = @Translation("Xapi content created state"),
 *  add_verb = @XapiVerb("author"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = NULL,
 * )
 */
class ContentCreatedState extends XapiStateBase {}
