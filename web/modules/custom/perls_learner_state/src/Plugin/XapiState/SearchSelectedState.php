<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\Component\Utility\Html;
use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define selected search result state.
 *
 * @XapiState(
 *  id = "xapi_selected",
 *  label = @Translation("Xapi selected state"),
 *  add_verb = @XapiVerb("selected"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = ""
 * )
 */
class SearchSelectedState extends XapiStateBase {

  /**
   * {@inheritDoc}
   */
  public function processExtraData($extra_data) {
    if (isset($extra_data->query)) {
      $this->statement->setResultResponse(Html::escape($extra_data->query));
    }
  }

}
