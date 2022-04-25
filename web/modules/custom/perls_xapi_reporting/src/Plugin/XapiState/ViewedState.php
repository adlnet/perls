<?php

namespace Drupal\perls_xapi_reporting\Plugin\XapiState;

use Drupal\perls_xapi_reporting\Plugin\XapiStateBase;

/**
 * Define launched state.
 *
 * @XapiState(
 *  id = "xapi_viewed_state",
 *  label = @Translation("Xapi viewed state"),
 *  add_verb = @XapiVerb("viewed"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = ""
 * )
 */
class ViewedState extends XapiStateBase {}
