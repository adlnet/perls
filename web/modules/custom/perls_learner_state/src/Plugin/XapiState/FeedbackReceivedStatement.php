<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\user\UserInterface;
use Drupal\perls_learner_state\Plugin\XapiStateBase;
use Drupal\perls_xapi_reporting\PerlsXapiActivityType;

/**
 * Define started course state.
 *
 * @XapiState(
 *  id = "xapi_feedback_received_statement",
 *  label = @Translation("Author received article feedback statement."),
 *  add_verb = @XapiVerb("received"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = "",
 * )
 */
class FeedbackReceivedStatement extends XapiStateBase {

  /**
   * {@inheritDoc}
   */
  protected function prepareStatement(int $timestamp = NULL, UserInterface $user = NULL) {
    if (empty($timestamp)) {
      $timestamp = $this->getStatementContent()->getCreatedTime();
    }
    // Here we extract the webform submission and set the node itself because
    // big part of statement is use it.
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submisson */
    $webform_submisson = $this->getStatementContent();
    $node = $webform_submisson->getSourceEntity();

    parent::prepareStatement($timestamp, $user);

    // Update Object.
    $this->statement->getObject()
      ->setName('feedback on ' . $node->label())
      ->setType(PerlsXapiActivityType::REVIEW);

    // Update Results.
    $this->statement
      ->addParentContext($node)
      ->setResultResponse($webform_submisson->getData()['feedback'])
      ->setResultScore($webform_submisson->getData()['content_relevant'], 1, -1)
      ->addResultExtensions([
        'http://xapi.gowithfloat.net/extension/form-id' => $webform_submisson->getWebform()->id(),
        'http://activitystrea.ms/schema/1.0/comment' => $webform_submisson->toUrl('canonical', ['absolute' => TRUE])->toString(),
      ]);
  }

}
