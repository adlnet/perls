<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define content archived state.
 *
 * @XapiState(
 *  id = "xapi_content_archived_state",
 *  label = @Translation("Xapi content archived state"),
 *  add_verb = @XapiVerb("archive"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = NULL,
 * )
 */
class ContentArchivedState extends XapiStateBase {}
