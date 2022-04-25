<?php

namespace Drupal\perls_adaptive_content\EventSubscriber;

use Drupal\perls_adaptive_content\AdaptiveContentServiceInterface;
use Drupal\xapi\Event\XapiStatementReceived;
use Drupal\xapi\XapiStatementHelper;
use Drupal\xapi\XapiVerb;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * This subcsriber listen to statement receive event to save the test results.
 */
class XapiEventSubscriber implements EventSubscriberInterface {

  /**
   * Xapi statement helper service.
   *
   * @var \Drupal\xapi\XapiStatementHelper
   */
  protected $xapiStatementHelper;

  /**
   * Adaptive Content Service.
   *
   * @var \Drupal\perls_adaptive_content\AdaptiveContentServiceInterface
   */
  protected $adaptiveContentService;

  /**
   * TestResultSubscriber constructor.
   *
   * @param \Drupal\xapi\XapiStatementHelper $helper
   *   The xapi statement helper service.
   * @param \Drupal\perls_adaptive_content\AdaptiveContentServiceInterface $adaptive_service
   *   The adaptive content service.
   */
  public function __construct(XapiStatementHelper $helper, AdaptiveContentServiceInterface $adaptive_service) {
    $this->xapiStatementHelper = $helper;
    $this->adaptiveContentService = $adaptive_service;

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[XapiStatementReceived::EVENT_NAME][] = ['statementReceived', 100];
    return $events;
  }

  /**
   * Uses complete xapi statements to update adaptive content.
   *
   * @param \Drupal\xapi\Event\XapiStatementReceived $event
   *   An event when somebody sent a request to statement endpoint.
   */
  public function statementReceived(XapiStatementReceived $event) {

    $statement = $event->getStatement();
    if (!isset($statement->verb)) {
      // Adding a temporary log message here to document a bug that we
      // have seen but can't reliably reproduce.
      \Drupal::logger('Xapi adaptive content Listener')->error('Xapi statement without verb: @statement', ['@statement' => json_encode($statement)]);
      return;
    }
    // We are only interested in completed statements.
    if ($statement->verb->id !== XapiVerb::completed()->getId()) {
      return;
    }
    $node = $this->xapiStatementHelper->getContentFromState($statement);
    $user = $this->xapiStatementHelper->getUserFromStatement($statement);

    // If we complete a tests we run tests for adaptive content.
    if ($user !== NULL && $node !== NULL && $node->getType() === 'test') {
      // Check adaptive content results.
      $this->adaptiveContentService->processTest($node, $user);
    }

    // We also need to check if we completed something marked as needs review.
  }

  /**
   * Help to decides that this statement is a quiz from a test statement.
   *
   * @param object $statement
   *   An xapi statement.
   *
   * @return bool
   *   TRUE if a statement is a test result statement otherwise FALSE.
   */
  private function isQuizFromTestStatement($statement) {
    if (isset($statement->result) && isset($statement->result->test_id)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get the result property from the statement.
   *
   * @param object $statement
   *   The xapi statement.
   *
   * @return object
   *   The test result.
   */
  private function getTestNode($statement) {
    $test = NULL;
    if (isset($statement->result) && isset($statement->result->test_id)) {
      $test_id = $statement->result->test_id;
      $test = \Drupal::entityTypeManager()->getStorage('node')->load($test_id);
    }
    return $test;
  }

  /**
   * Check to see if the answer was correct.
   */
  private function correctAnswer($statement) {
    $success = FALSE;
    if (isset($statement->result) && isset($statement->result->success)) {
      $success = $statement->result->success;
    }
    return $success;
  }

}
