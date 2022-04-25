<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define content contributed state.
 *
 * @XapiState(
 *  id = "xapi_content_contributed_state",
 *  label = @Translation("Xapi content contributed state"),
 *  add_verb = @XapiVerb("give"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = NULL,
 * )
 */
class ContentContributedState extends XapiStateBase {}
