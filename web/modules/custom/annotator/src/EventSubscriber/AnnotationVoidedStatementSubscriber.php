<?php

namespace Drupal\annotator\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\annotator\XapiVerbAnnotation;
use Drupal\xapi\Event\XapiStatementReceived;
use Drupal\xapi\LRSRequestGenerator;
use Drupal\xapi\XapiStatementHelper;
use Drupal\xapi\XapiVerb;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Sync xapi statement which contains content feedback.
 */
class AnnotationVoidedStatementSubscriber implements EventSubscriberInterface {

  /**
   * The xapi statement helper service.
   *
   * @var \Drupal\xapi\XapiStatementHelper
   */
  protected $statementHelper;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorage
   */
  protected $userStorage;

  /**
   * Logged in user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * LRS Request generator.
   *
   * @var \Drupal\xapi\LRSRequestGenerator
   */
  protected $lrsRequestGenerator;

  /**
   * Event listener which is listing to XapiStatementReceived event.
   *
   * @param \Drupal\xapi\XapiStatementHelper $statement_helper
   *   The statement helper service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Drupal entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Logged in user.
   * @param \Drupal\xapi\LRSRequestGenerator $lrs_request_generator
   *   The LRS request generator service.
   */
  public function __construct(
    XapiStatementHelper $statement_helper,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    LRSRequestGenerator $lrs_request_generator
  ) {
    $this->statementHelper = $statement_helper;
    $this->userStorage = $entity_type_manager->getStorage('user');
    $this->currentUser = $current_user;
    $this->lrsRequestGenerator = $lrs_request_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[XapiStatementReceived::EVENT_NAME][] = ['getVoidedStatements', 100];
    return $events;
  }

  /**
   * Received the voided statements from app.
   *
   * @param \Drupal\xapi\Event\XapiStatementReceived $event
   *   The event object.
   */
  public function getVoidedStatements(XapiStatementReceived $event) {
    $statement = $event->getStatement();

    if (!$this->isValidStatementForEvent($statement)) {
      return;
    }

    if (!$this->isValidAgent($statement)) {
      throw new AccessDeniedHttpException($this->t('You are not authorized to void this statemenet.'));
    }

    // Get the original statement to get the activity id of state.
    $original_statement = $this->getOriginalAnnotationStatement($statement);
    if (!isset($original_statement->object->id)) {
      // If the statement doesn't exist try it again, maybe it was already void.
      $original_statement = $this->getOriginalAnnotationStatement($statement, TRUE);
    }

    $annotated_verb = XapiVerbAnnotation::annotated();
    if (!isset($original_statement->object->id) || !isset($original_statement->verb->id) ||
    $original_statement->verb->id != $annotated_verb->getId()) {
      // We can't find the original statement to query the node info.
      // Or the voided statement is not for an annotation.
      return;
    }

    $original_object_id = $original_statement->object->id;

    // Get the state for that object to update the annotations.
    $original_state = $this->getOriginalState($statement, $original_object_id);
    if (empty($original_state) || !property_exists($original_state, "highlights")) {
      return;
    }
    $original_annotations_array = (array) $original_state->highlights;
    if (empty($original_annotations_array) || !is_array($original_annotations_array)) {
      return;
    }

    $new_annotations = array_filter($original_annotations_array, function ($annotation) use ($original_statement) {
      return isset($annotation->statement_id) ? $annotation->statement_id != $original_statement->id : FALSE;
    });

    $original_state->highlights = array_values($new_annotations);
    // Send filtered set of annotations.
    return $this->lrsRequestGenerator->state('annotations', (array) $statement->actor, $original_object_id, \json_encode($original_state));
  }

  /**
   * Checks the statement's verb.
   *
   * @param object $statement
   *   The statement which contains the verb and object to check.
   *
   * @returns bool
   *  Returns true if the statement is valid.
   */
  private function isValidStatementForEvent($statement) {
    $verb_id = $statement->verb->id;
    $void_id = XapiVerb::voided();
    $orginal_statement_id = $statement->object->id;
    if (empty($verb_id) || empty($orginal_statement_id) || $verb_id != $void_id->getId()) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Checks the agent against the current user.
   *
   * @param object $statement
   *   The statement which contains the agent to check.
   *
   * @returns bool
   *  Returns true if the agent is valid.
   */
  private function isValidAgent($statement) {
    $user_from_agent = $this->statementHelper->getUserFromStatement($statement);
    $agent_uuid = $user_from_agent->uuid();
    $uuid = $this->userStorage->load($this->currentUser->id())->uuid();
    return $agent_uuid === $uuid;
  }

  /**
   * Gets the original statement which was voided.
   *
   * @param object $statement
   *   The statement which contains the id of the original statement.
   * @param bool $isVoidedStatement
   *   Sets voidedStatementId if true.
   *
   * @returns {object}
   *  Returns the original statement.
   */
  private function getOriginalAnnotationStatement($statement, $isVoidedStatement = FALSE) {
    // Get the original statement's object id so we can
    // get the annotations for that object.
    $orginal_statement_id = $statement->object->id;
    $response = $this->lrsRequestGenerator->getStatement($orginal_statement_id, $isVoidedStatement);
    return \json_decode($response->getContent());
  }

  /**
   * Gets the current set of annotations for XAPI state.
   *
   * @param object $statement
   *   The statement which contains the agent.
   * @param string $object_id
   *   The activity id to query for state.
   *
   * @returns {array}
   *  Returns an array of annotations.
   */
  private function getOriginalState($statement, $object_id) {
    $state_response = $this->lrsRequestGenerator->state('annotations', (array) $statement->actor, $object_id);
    if (empty($state_response)) {
      return;
    }
    return \json_decode($state_response->getContent());
  }

}
