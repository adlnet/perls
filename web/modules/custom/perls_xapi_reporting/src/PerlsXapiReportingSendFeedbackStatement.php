<?php

namespace Drupal\perls_xapi_reporting;

use Drupal\perls_learner_state\Plugin\XapiStateManager;
use Drupal\user\EntityOwnerInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Helps to send xapi statement when a webform feedback is created/updated.
 */
class PerlsXapiReportingSendFeedbackStatement {

  /**
   * Value of up-voted feedback field.
   */
  const UPVOTED_CONTENT_FEEDBACK = '1';

  /**
   * Value of down-voted feedback field.
   */
  const DOWNVOTED_CONTENT_FEEDBACK = '-1';

  /**
   * Xapi statement plugin type manager.
   *
   * @var \Drupal\perls_learner_state\Plugin\XapiStateManager
   */
  protected $statementManager;

  /**
   * A xapi statement that we will send to LRS server.
   *
   * @var \Drupal\perls_learner_state\Plugin\ArticleFeedbackRateBase
   */
  protected $statement;

  /**
   * Sends xapi statements about user's feedbacks.
   *
   * @param \Drupal\perls_learner_state\Plugin\XapiStateManager $statement_manager
   *   Xapi statement plugin manager.
   */
  public function __construct(XapiStateManager $statement_manager) {
    $this->statementManager = $statement_manager;
  }

  /**
   * Sends xapi statement.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A drupal webform submission.
   */
  public function sendStatement(WebformSubmissionInterface $webform_submission) {
    $this->statementManager->sendStatement($this->getStatementTemplateId($webform_submission), $webform_submission);

    // We also want to give feedback to the author.
    $entity = $webform_submission->getSourceEntity();
    if (!$entity instanceof EntityOwnerInterface) {
      return;
    }

    $this->statementManager->sendStatement('xapi_feedback_received_statement', $webform_submission, $entity->getOwner());
  }

  /**
   * Gets the template (plugin) ID based on the webform response.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform.
   *
   * @return string
   *   The template (plugin) ID.
   *
   * @throws InvalidArgumentException
   *   Thrown if the webform does not contain an upvote or a downvote.
   */
  protected function getStatementTemplateId(WebformSubmissionInterface $webform_submission): string {
    $submission_data = $webform_submission->getData();
    switch ($submission_data['content_relevant']) {
      case self::UPVOTED_CONTENT_FEEDBACK:
        return 'xapi_feedback_upvoted_statement';

      case self::DOWNVOTED_CONTENT_FEEDBACK:
        return 'xapi_feedback_downvoted_statement';

      default:
        throw new \InvalidArgumentException('The webform must contain an upvote or downvote.');
    }
  }

}
