<?php

namespace Drupal\xapi\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\xapi\Event\XapiStatementReceived;
use Drupal\xapi\LRSRequestGenerator;
use Drupal\xapi\LRSServer;
use Drupal\xapi\XapiStatementHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Handles passing LRS statements to the remote LRS & tracking user completion.
 *
 * See 'xapi.routing.yml' for the routing info.
 */
class LRSController extends ControllerBase {
  /**
   * Manage LRS requests.
   *
   * @var \Drupal\xapi\LRSServer
   */
  protected $lrsService;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Current logged in drupal user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The LRS request generator.
   *
   * @var \Drupal\xapi\LRSRequestGenerator
   */
  protected $lrsRequestGenerator;

  /**
   * The xAPI statement helper.
   *
   * @var \Drupal\xapi\XapiStatementHelper
   */
  protected $statementHelper;

  /**
   * Configures the endpoint URL and authentication for a new controller.
   *
   * @param \Drupal\xapi\LRSServer $lrs
   *   LRS server communications service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   Current drupal user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\xapi\LRSRequestGenerator $lrs_request_generator
   *   LRS request generator.
   * @param \Drupal\xapi\XapiStatementHelper $statement_helper
   *   Statement helper.
   */
  public function __construct(
    LRSServer $lrs,
    EventDispatcherInterface $event_dispatcher,
    AccountProxy $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    LRSRequestGenerator $lrs_request_generator,
    XapiStatementHelper $statement_helper) {
    $this->lrsService = $lrs;
    $this->eventDispatcher = $event_dispatcher;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->lrsRequestGenerator = $lrs_request_generator;
    $this->statementHelper = $statement_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lrs.request'),
      $container->get('event_dispatcher'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('lrs.request_generator'),
      $container->get('xapi.xapi_statement_helper')
    );
  }

  /**
   * Respond to `/activities` requests to the LRS.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request received from the eLearning content.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A formatted response from the LRS.
   */
  public function activities(SymfonyRequest $request) {
    return $this->lrsService->sendRequest($request);
  }

  /**
   * Respond to `/activities/profile` requests to the LRS.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request received from the eLearning content.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A formatted response from the LRS.
   */
  public function activitiesProfile(SymfonyRequest $request) {
    return $this->lrsService->sendRequest($request);
  }

  /**
   * Respond to `activities/state` requests to the LRS.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request received from the eLearning content.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A formatted response from the LRS.
   */
  public function activitiesState(SymfonyRequest $request) {
    $query_string = $request->getQueryString();
    if (empty($query_string)) {
      throw new BadRequestHttpException($this->t('A valid agent is required. The query string is empty.'));
    }

    parse_str($query_string, $query);
    if (empty($query["agent"])) {
      throw new BadRequestHttpException($this->t('A valid agent is required. Agent is missing from the query string.'));
    }

    $agent = json_decode($query["agent"]);
    $user = $this->statementHelper->getUserFromActor($agent);

    // If the agent on this request is a Drupal user,
    // then we must make sure the current user has permission
    // to access/modify the activity state.
    if ($user) {
      if ($user->id() !== $this->currentUser->id() && !$this->currentUser->hasPermission('administer users')) {
        throw new AccessDeniedHttpException($this->t('You are not authorized to access this activity state.'));
      }

      // Modify request to match IFI settings.
      $request = $this->lrsRequestGenerator->modifyLrsStateAgent($request, $user);
    }

    return $this->lrsService->sendRequest($request);
  }

  /**
   * Respond to `/statements` requests to the LRS.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request received from the eLearning content.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A formatted response from the LRS.
   */
  public function statements(SymfonyRequest $request) {
    switch ($request->getMethod()) {
      case 'PUT':
        $statement = json_decode($request->getContent());
        $statement_id = $request->query->get('statementId');
        if (!$statement_id) {
          throw new BadRequestHttpException('Request parameter statementId is required');
        }
        if (isset($statement->id) && $statement_id !== $statement->id) {
          throw new BadRequestHttpException("Request parameter statementId ($statement_id) must match ID of statement ($statement->id)");
        }

        $statement = $this->triggerStatementEvent($statement);

        $request = $this->lrsRequestGenerator->modifyLrsStatementContent($request, $statement);
        break;

      case 'POST':
        // A POST request may store a single statement or multiple statements.
        // For simplicity, we'll always treat it as multiple statements.
        $statements = json_decode($request->getContent()) ?: [];
        if (!is_array($statements)) {
          $statements = [$statements];
        }

        foreach ($statements as $statement) {
          $this->triggerStatementEvent($statement);
        }

        $request = $this->lrsRequestGenerator->modifyLrsStatementContent($request, $statements);
        break;

      case 'GET':
        // GET requests are unmodified.
        break;
    }

    return $this->lrsService->sendRequest($request);
  }

  /**
   * Respond to `/agents` requests to the LRS.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request received from the eLearning content.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A formatted response from the LRS.
   */
  public function agents(SymfonyRequest $request) {
    return $this->lrsService->sendRequest($request);
  }

  /**
   * Respond to `/agents/profile` requests to the LRS.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request received from the eLearning content.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A formatted response from the LRS.
   */
  public function agentsProfile(SymfonyRequest $request) {
    return $this->lrsService->sendRequest($request);
  }

  /**
   * Respond to `/about` requests to the LRS.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request received from the eLearning content.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A formatted response from the LRS.
   */
  public function about(SymfonyRequest $request) {
    return $this->lrsService->sendRequest($request);
  }

  /**
   * Broadcast an xapi_statement_received event.
   *
   * @param object $statement
   *   The statement object.
   *
   * @return object
   *   The modified statement object.
   */
  protected function triggerStatementEvent($statement) {
    if (!$this->isValidStatement($statement)) {
      $this->getLogger('xapi')->warning('Invalid Statement Received: @statement', ['@statement' => json_encode($statement)]);
      return $statement;
    }
    $event = new XapiStatementReceived($statement);
    $this->eventDispatcher->dispatch(XapiStatementReceived::EVENT_NAME, $event);
    return $event->getStatement();
  }

  /**
   * Check if Statements has required fields set.
   *
   * This method just checks api format. It does not check
   * if user/object exists.
   *
   * @param object $statement
   *   The statement object.
   *
   * @return bool
   *   True if valid, otherwise False.
   */
  protected function isValidStatement($statement) {
    // Check for NULL or empty.
    if (!$statement || empty($statement)) {
      return FALSE;
    }
    // Check actor is set.
    // @todo Add openid validation.
    if (
      !isset($statement->actor) ||
      (isset($statement->actor->account) && (!isset($statement->actor->account->name) || empty($statement->actor->account->name) || !is_string($statement->actor->account->name))) ||
      (isset($statement->actor->mbox) && (empty($statement->actor->mbox) || !is_string($statement->actor->mbox))) ||
      (isset($statement->actor->mbox_sha1sum) && (empty($statement->actor->mbox_sha1sum) || !is_string($statement->actor->mbox_sha1sum)))
    ) {
      return FALSE;
    }
    // Check the verb is set.
    if (
      !isset($statement->verb)
      || !isset($statement->verb->id)
      || empty($statement->verb->id)
      || !is_string($statement->verb->id)
    ) {
      return FALSE;
    }
    // Check the object is set.
    if (
      !isset($statement->object)
      || !isset($statement->object->id)
      || empty($statement->object->id)
      || !is_string($statement->object->id)
    ) {
      return FALSE;
    }
    return TRUE;
  }

}
