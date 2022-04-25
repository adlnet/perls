<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\ArticleFeedbackRateBase;

/**
 * Define started course state.
 *
 * @XapiState(
 *  id = "xapi_feedback_upvoted_statement",
 *  label = @Translation("Xapi statement upvoted article."),
 *  add_verb = @XapiVerb("votedUp"),
 *  remove_verb = NULL,
 *  notifyOnXapi = TRUE,
 *  flag = "",
 * )
 */
class FeedbackUpVotedStatement extends ArticleFeedbackRateBase {}
