<?php

namespace Drupal\perls_learner_state\Plugin;

use Drupal\user\UserInterface;

/**
 * A base class which reports xapi statement about user's feedback.
 */
class ArticleFeedbackRateBase extends XapiStateBase {

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
    $this->setStatementContent($webform_submisson->getSourceEntity());
    parent::prepareStatement($timestamp, $user);

    if (!empty($webform_submisson->getData()['feedback'])) {
      $this->statement->setResultResponse($webform_submisson->getData()['feedback']);
    }

    $this->statement->addResultExtensions([
      'http://xapi.gowithfloat.net/extension/form-id' => $webform_submisson->getWebform()->id(),
    ]);

  }

}
