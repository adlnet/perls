<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define bookmark state.
 *
 * @XapiState(
 *  id = "xapi_bookmark_state",
 *  label = @Translation("Xapi bookmark state"),
 *  add_verb = @XapiVerb("saved"),
 *  remove_verb = @XapiVerb("unsaved"),
 *  notifyOnXapi = TRUE,
 *  flag = "bookmark"
 * )
 */
class BookmarkState extends XapiStateBase {}
