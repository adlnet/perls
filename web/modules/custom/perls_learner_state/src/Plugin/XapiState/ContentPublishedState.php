<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define content published state.
 *
 * @XapiState(
 *  id = "xapi_content_published_state",
 *  label = @Translation("Xapi content published state"),
 *  add_verb = @XapiVerb("approve"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = NULL,
 * )
 */
class ContentPublishedState extends XapiStateBase {}
