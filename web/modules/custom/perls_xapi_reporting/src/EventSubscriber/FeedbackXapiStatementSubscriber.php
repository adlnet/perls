<?php

namespace Drupal\perls_xapi_reporting\EventSubscriber;

use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\perls_xapi_reporting\PerlsXapiVerb;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\xapi\Event\XapiStatementReceived;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\xapi\XapiStatementHelper;
use Drupal\perls_xapi_reporting\PerlsXapiReportingSendFeedbackStatement;

/**
 * Sync xapi statement which contains content feedback.
 */
class FeedbackXapiStatementSubscriber implements EventSubscriberInterface {

  /**
   * The xapi statement helper service.
   *
   * @var \Drupal\xapi\XapiStatementHelper
   */
  protected $statementHelper;

  /**
   * Drupal entity storage interface.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $currentRequest;

  /**
   * Event listener which is listing to XapiStatementReceived event.
   *
   * @param \Drupal\xapi\XapiStatementHelper $statement_helper
   *   The statement helper service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Drupal entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack service.
   */
  public function __construct(
    XapiStatementHelper $statement_helper,
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request_stack
  ) {
    $this->statementHelper = $statement_helper;
    $this->entityStorage = $entity_type_manager->getStorage('webform_submission');
    $this->currentRequest = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[XapiStatementReceived::EVENT_NAME][] = [
      'getFeedbackStatements',
      -100,
    ];
    return $events;
  }

  /**
   * Received the webform feedback from app.
   *
   * @param \Drupal\xapi\Event\XapiStatementReceived $event
   *   The event object.
   */
  public function getFeedbackStatements(XapiStatementReceived $event) {
    $statement = $event->getStatement();
    $verb_id = $statement->verb->id;
    // This way I try to decide the source of the request, if the form_build_id
    // doesn't exists, it means the request are coming from app.
    $form_id = $this->currentRequest->request->get('form_build_id');
    $verbs = [
      PerlsXapiVerb::votedUp()->getId(),
      PerlsXapiVerb::votedDown()->getId(),
    ];
    if (empty($form_id) &&
    in_array($verb_id, $verbs)) {
      $user = $this->statementHelper->getUserFromStatement($statement);
      $node = $this->statementHelper->getContentFromState($statement);
      if ($user && $node) {
        /** @var \Drupal\webform\Entity\WebformSubmission $existing_submission */
        $existing_submission = $this->loadWebformSubmission($node, $user);
        if ($existing_submission) {
          $this->updateWebformSubmission($statement, $existing_submission);
        }
        else {
          $this->createWebformSubmission($statement, $node, $user);
        }
      }
    }
  }

  /**
   * Loads an existing webform submission.
   *
   * @param \Drupal\node\NodeInterface $parent_content
   *   The content which get the feedback.
   * @param \Drupal\user\UserInterface $user
   *   The drupal user who left the comment.
   *
   * @return \Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|null
   *   The load webform submission otherwise NULL.
   */
  protected function loadWebformSubmission(NodeInterface $parent_content, UserInterface $user) {
    $query = $this->entityStorage->getQuery();
    $query->condition('webform_id', 'content_specific_webform');
    $query->condition('uid', $user->id());
    $query->condition('entity_id', $parent_content->id());
    $query->condition('locked', 0);
    $webform_ids = $query->execute();
    if (!empty($webform_ids)) {
      return WebformSubmission::load(reset($webform_ids));
    }
    else {
      return NULL;
    }
  }

  /**
   * Update an existing webform submission with xapi statement.
   *
   * @param object $statement
   *   An xapi statement which was create from json.
   * @param \Drupal\webform\WebformSubmissionInterface $existing_submission
   *   An existing webfrom submission.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function updateWebformSubmission($statement, WebformSubmissionInterface $existing_submission) {
    $verb_id = $statement->verb->id;
    $data = $existing_submission->getData();
    if ($verb_id === PerlsXapiVerb::votedUp()->getId() && isset($existing_submission->getData()['content_relevant'])) {
      $data['content_relevant'] = PerlsXapiReportingSendFeedbackStatement::UPVOTED_CONTENT_FEEDBACK;
    }
    elseif ($verb_id === PerlsXapiVerb::votedDown()->getId() && isset($existing_submission->getData()['content_relevant'])) {
      $data['content_relevant'] = PerlsXapiReportingSendFeedbackStatement::DOWNVOTED_CONTENT_FEEDBACK;
    }

    if (isset($statement->result->response)) {
      $data['feedback'] = $statement->result->response;
    }

    if (!empty($statement->timestamp)) {
      $existing_submission->setCreatedTime(strtotime($statement->timestamp));
    }
    $existing_submission->setData($data)->save();
  }

  /**
   * Create a new webform submisson based on xapi statement.
   *
   * @param object $statement
   *   An xapi statement object form json format.
   * @param \Drupal\node\NodeInterface $parent_content
   *   The node object which get the feedback.
   * @param \Drupal\user\UserInterface $user
   *   A drupal user who send the feedback.
   */
  protected function createWebformSubmission($statement, NodeInterface $parent_content, UserInterface $user) {
    $verb_id = $statement->verb->id;
    if ($verb_id === PerlsXapiVerb::votedUp()->getId()) {
      $content_relevant_field = PerlsXapiReportingSendFeedbackStatement::UPVOTED_CONTENT_FEEDBACK;
    }
    elseif ($verb_id === PerlsXapiVerb::votedDown()->getId()) {
      $content_relevant_field = PerlsXapiReportingSendFeedbackStatement::DOWNVOTED_CONTENT_FEEDBACK;
    }
    else {
      return;
    }

    $feedback = '';
    if (isset($statement->result->response)) {
      $feedback = $statement->result->response;
    }

    WebformSubmission::create([
      'webform_id' => 'content_specific_webform',
      'entity_type' => 'node',
      'entity_id' => $parent_content->id(),
      'uid' => $user->id(),
      'data' => [
        'content_relevant' => $content_relevant_field,
        'feedback' => $feedback,
      ],
    ])->save();
  }

}
