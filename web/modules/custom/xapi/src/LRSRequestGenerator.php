<?php

namespace Drupal\xapi;

use Drupal\Core\Url;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Routing\AccessAwareRouterInterface;
use Drupal\Core\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Generate LRS request for LRS endpoints.
 */
class LRSRequestGenerator {

  /**
   * Controller resolver.
   *
   * @var \Drupal\Core\Controller\ControllerResolverInterface
   */
  protected $controllerResolver;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $request;

  /**
   * Argument resolver.
   *
   * @var \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface
   */
  protected $argumentResolver;

  /**
   * The current route.
   *
   * @var \Drupal\Core\Routing\AccessAwareRouterInterface
   */
  private $router;

  /**
   * Drupal account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * Drupal entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Generate a call to a controller.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Current request.
   * @param \Drupal\Core\Controller\ControllerResolverInterface $controller_resolver
   *   Controller resolver.
   * @param \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface $argument_resolver
   *   Argument resolver.
   * @param \Drupal\Core\Routing\AccessAwareRouterInterface $router
   *   The current route.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Drupal entity type manager service.
   */
  public function __construct(
    RequestStack $request_stack,
    ControllerResolverInterface $controller_resolver,
    ArgumentResolverInterface $argument_resolver,
    AccessAwareRouterInterface $router,
    AccountSwitcherInterface $account_switcher,
    EntityTypeManagerInterface $entity_type_manager) {
    $this->request = $request_stack->getCurrentRequest();
    $this->controllerResolver = $controller_resolver;
    $this->argumentResolver = $argument_resolver;
    $this->router = $router;
    $this->accountSwitcher = $account_switcher;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Send a post request to /lrs/statements endpoint.
   *
   * @param array $statements
   *   An array of LRS statements.
   * @param int $uid
   *   A user id, we will use it to authenticate for LRS request.
   */
  public function sendStatements(array $statements, $uid = NULL) {
    $url = Url::fromRoute('xapi.statements');

    return $this->compileAndSendRequest($url, Request::METHOD_POST, [], json_encode($statements), $uid);
  }

  /**
   * Send a get request to /lrs/statements endpoint.
   *
   * @param string $statementId
   *   The StatementId of the statement that was voided.
   * @param bool $attachments
   *   If true, the LRS uses the multipart response format and includes all
   *   attachments as described previously.
   *   If false, the LRS sends the prescribed response with Content-Type
   *   application/json and does not send attachment data.
   * @param string $format
   *   Allowed values are ids, exact, or canonical.
   * @param int $uid
   *   A user id, we will use it to authenticate for LRS request.
   */
  public function getStatement(string $statementId = NULL, bool $attachments = NULL, string $format = NULL, $uid = NULL) {
    $query_parameters = [];
    $query_parameters["statementId"] = $statementId;
    if ($attachments) {
      $query_parameters["attachments"] = $attachments;
    }
    if ($format) {
      $query_parameters["format"] = $format;
    }
    return $this->getStatements($query_parameters, $uid);
  }

  /**
   * Send a get request to /lrs/statements endpoint for a voided statement.
   *
   * @param string $voidedStatementId
   *   The StatementId of the statement that was voided.
   * @param bool $attachments
   *   If true, the LRS uses the multipart response format and includes all
   *   attachments as described previously.
   *   If false, the LRS sends the prescribed response with Content-Type
   *   application/json and does not send attachment data.
   * @param string $format
   *   Allowed values are ids, exact, or canonical.
   * @param int $uid
   *   A user id, we will use it to authenticate for LRS request.
   */
  public function getVoidedStatement(string $voidedStatementId = NULL, bool $attachments = NULL, string $format = NULL, $uid = NULL) {
    $query_parameters = [];
    $query_parameters["voidedStatementId"] = $voidedStatementId;
    if ($attachments) {
      $query_parameters["attachments"] = $attachments;
    }
    if ($format) {
      $query_parameters["format"] = $format;
    }
    return $this->getStatements($query_parameters, $uid);
  }

  /**
   * Convenient way to GET statements via to /lrs/statements endpoint.
   *
   * @param Drupal\xapi\XapiActor $actor
   *   The actor to query.
   * @param Drupal\xapi\XapiVerb $verb
   *   The verb to query.
   * @param string $activity_id
   *   The activity to query.
   * @param int $uid
   *   A user id, we will use it to authenticate for LRS request.
   */
  public function getStatementsByActor(XapiActor $actor, XapiVerb $verb = NULL, string $activity_id = NULL, int $uid = NULL) {
    $query_parameters = [];
    $query_parameters["agent"] = json_encode($actor);
    if (!empty($verb)) {
      $query_parameters["verb"] = $verb->getId();
    }
    if (!empty($activity_id)) {
      $query_parameters["activity"] = $activity_id;
    }
    return $this->getStatements($query_parameters, $uid);
  }

  /**
   * Send a get request to /lrs/statements endpoint.
   *
   * @param array $query_parameters
   *   The query_parameters for the statement.
   *   This should only pass parameters defined in
   *   https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md#213-get-statements
   *   BEWARE OF PARAMETERS CASE.
   * @param int $uid
   *   A user id, we will use it to authenticate for LRS request.
   */
  public function getStatements(array $query_parameters, int $uid = NULL) {
    $url = Url::fromRoute('xapi.statements');
    return $this->compileAndSendRequest($url, Request::METHOD_GET, $query_parameters, NULL, $uid);
  }

  /**
   * Send a get request to /lrs/statements endpoint.
   *
   * @param string $stateId
   *   The stateId to query.
   * @param array $agent
   *   The actor to query.
   * @param string $activity_id
   *   The activity to query.
   * @param string|resource|null $content
   *   The content to save in the state.
   * @param int $uid
   *   A user id, we will use it to authenticate for LRS request.
   */
  public function state(string $stateId, array $agent, string $activity_id, $content = NULL, $uid = NULL) {
    $url = Url::fromRoute('xapi.activities.state');
    $query_parameters = [];

    if (empty($stateId) || empty($agent) || empty($activity_id)) {
      return;
    }
    $query_parameters["stateId"] = $stateId;
    $query_parameters["activityId"] = $activity_id;
    $query_parameters["agent"] = json_encode($agent);

    $method = $content == NULL ? Request::METHOD_GET : Request::METHOD_POST;

    return $this->compileAndSendRequest($url, $method, $query_parameters, $content, $uid);
  }

  /**
   * Make a new request.
   *
   * @param \Drupal\Core\Url $url
   *   The url of the request.
   * @param string $method
   *   The actor to query.
   * @param array $query_parameters
   *   The query parameters.
   * @param string|resource|null $content
   *   The content to save in the state.
   *
   * @return bool|\Symfony\Component\HttpFoundation\Request
   *   The new generated request otherwise FALSE;
   */
  private function generateNewRequest(Url $url, $method = Request::METHOD_GET, array $query_parameters = [], $content = NULL) {
    $server_settings = $this->request->server->all();
    if (empty($server_settings['CONTENT_TYPE']) || $server_settings['CONTENT_TYPE'] != 'application/json') {
      $server_settings['CONTENT_TYPE'] = 'application/json';
    }

    if (($method == Request::METHOD_POST || $method == Request::METHOD_PUT) && $content != NULL) {
      $url->setOption('query', $query_parameters);
      $query_parameters = [];
    }

    $request = Request::create(
      $url->toString(),
      $method,
      $query_parameters,
      [],
      [],
      $server_settings,
      $content
    );

    $request->headers->set('x-experience-api-version', '1.0.3');
    $original_context = $this->router->getContext();
    $context = new RequestContext();
    $context->fromRequest($request);

    try {
      // We need this function call because it will populate the request with
      // attributes like controller.
      $this->router->setContext($context);
      $this->router->matchRequest($request);
    }
    catch (\Exception $e) {
      watchdog_exception('xapi', $e);
      return FALSE;
    }
    finally {
      $this->router->setContext($original_context);
    }

    if (!$request->headers->get('X-Experience-API-Version')) {
      $request->headers->set('X-Experience-API-Version', '1.0.3');
    }

    return $request;
  }

  /**
   * Regenerate a xapi statement request and update statement content.
   *
   * The Request option doesn't allow to modify the content body of an existing
   * request, so we need to "clone" the old one and update it with the modified
   * statement object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The old xapi statement request.
   * @param object $modified_statement
   *   The modified statement what we get back from event handler.
   */
  public function modifyLrsStatementContent(Request $request, $modified_statement) {
    $url = Url::fromUri($request->getUri());
    $query_parameter = [];
    if ($request->getMethod() === Request::METHOD_GET) {
      $query_parameter = $request->query->all();
    }
    elseif ($request->getMethod() === Request::METHOD_POST) {
      $query_parameter = $request->request->all();
    }

    $new_request = $this->generateNewRequest(
      $url,
      $request->getMethod(),
      $query_parameter,
      json_encode($modified_statement)
    );

    // Set the old headers to the new request.
    $new_request->headers = $request->headers;

    return $new_request;
  }

  /**
   * Regenerate the xapi state request and update the agent.
   *
   * The Request option doesn't allow to modify the query parameters of an
   * existing request, so we need to "clone" the old one and update it with
   * the new agent object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The old xapi statement request.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user of this current request.
   */
  public function modifyLrsStateAgent(Request $request, AccountInterface $user) {
    $url = Url::fromUri($request->getUri());

    $query_parameter = [];
    $query_parameter = $request->query->all();

    $agent = XapiActor::createWithUser($user);
    $query_parameter['agent'] = json_encode($agent);

    $new_request = $this->generateNewRequest(
      $url,
      $request->getMethod(),
      $query_parameter,
      $request->getContent()
    );

    // Set the old headers to the new request.
    $new_request->headers = $request->headers;

    return $new_request;
  }

  /**
   * Makes and sends a request to an LRS endpoint.
   *
   * @param \Drupal\Core\Url $url
   *   The url of the request.
   * @param string $method
   *   The actor to query.
   * @param array $query_parameters
   *   The query parameters.
   * @param string|resource|null $content
   *   The content to save in the state.
   * @param int $uid
   *   A user id, we will use it to authenticate for LRS request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response of controller.
   */
  private function compileAndSendRequest(Url $url, $method = Request::METHOD_GET, array $query_parameters = [], $content = NULL, $uid = NULL) {
    try {
      $response = new Response('', 200);
      // Sometimes the request runs as anonymous user and we need to create
      // session otherwise we cannot send request to statement endpoint because
      // it is protected by permission.
      if (isset($uid)) {
        $user = $this->entityTypeManager->getStorage('user')->load($uid);
        $this->accountSwitcher->switchTo($user);
      }

      $request = $this->generateNewRequest($url, $method, $query_parameters, $content);

      if ($request) {
        $controller = $this->controllerResolver->getController($request);
        $arguments = $this->argumentResolver->getArguments($request, $controller);
        $response = call_user_func_array($controller, $arguments);

        if (!$response) {
          $response->setStatusCode('500');
          $response->setContent(t('An error occurred during the request'));
        }
      }

      return $response;
    } finally {
      if (isset($uid)) {
        $this->accountSwitcher->switchBack();
      }
    }
  }

}
