<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\ArticleFeedbackRateBase;

/**
 * Define started course state.
 *
 * @XapiState(
 *  id = "xapi_feedback_downvoted_statement",
 *  label = @Translation("Xapi statement downvoted article."),
 *  add_verb = @XapiVerb("votedDown"),
 *  remove_verb = NULL,
 *  notifyOnXapi = TRUE,
 *  flag = "",
 * )
 */
class FeedbackDownVotedStatement extends ArticleFeedbackRateBase {}
