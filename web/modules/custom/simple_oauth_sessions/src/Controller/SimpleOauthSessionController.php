<?php

namespace Drupal\simple_oauth_sessions\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simple_oauth\Authentication\Provider\SimpleOauthAuthenticationProvider;
use Drupal\simple_oauth_sessions\UserNotFoundException;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpFoundation\Request;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Provide response to /start-session/{access_token} path.
 *
 * @package Drupal\simple_oauth_sessions\Controller
 */
class SimpleOauthSessionController extends ControllerBase {

  /**
   * Simple OAuth Authentication provider.
   *
   * @var \Drupal\simple_oauth\Authentication\Provider\SimpleOauthAuthenticationProvider
   */
  protected $simpleOauthProvider;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entity_type_manager,
    SimpleOauthAuthenticationProvider $simple_oauth_provider
  ) {
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entity_type_manager;
    $this->simpleOauthProvider = $simple_oauth_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('simple_oauth.authentication.simple_oauth')
    );
  }

  /**
   * Get session to user based on access token.
   *
   * @param \Psr\Http\Message\ServerRequestInterface $request
   *   The request.
   * @param string $access_token
   *   A JWT coded access token.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   A Response object based on the validation result.
   */
  public function getSession(ServerRequestInterface $request, $access_token) {
    $params = $request->getQueryParams();
    if (empty($access_token) && isset($params['access_token'])) {
      $access_token = $params['access_token'];
    }

    try {
      $user = $this->getUser($access_token);
    }
    catch (UserNotFoundException $e) {
      throw new UnauthorizedHttpException('You do not have permission to access this', $e->getMessage(), $e->getPrevious());
    }

    if (!$user) {
      // This should be impossible since `SimpleOauthSessionController::getUser`
      // requires a return value. But we'll be extra certain since we are
      // dealing with user authentication.
      throw new \LogicException('No valid user was found');
    }

    user_login_finalize($user);

    // Clear out any queued up messages.
    \Drupal::messenger()->deleteAll();
    return $this->redirect('<front>');
  }

  /**
   * Determines the user that owns an access token.
   *
   * This will ensure that the access token is valid, that it is not expired,
   * and that it refers to a valid user.
   *
   * @param string $access_token
   *   An access token.
   *
   * @return \Drupal\user\UserInterface
   *   The user that owns the access token.
   *
   * @throws \Drupal\simple_oauth_sessions\UserNotFoundException
   *   Thrown when the access token does not belong to a valid user.
   */
  private function getUser(string $access_token): UserInterface {
    // Creates a stub request to ask the simple_oauth module
    // to validate the access token.
    $request = Request::create('/');
    $request->headers->set('Authorization', $access_token);

    try {
      $account = $this->simpleOauthProvider->authenticate($request);
    }
    catch (HttpException $e) {
      // `SimpleOauthAuthenticationProvider::authenticate` is expected
      // to throw an HttpException (401) when the access token is invalid.
      throw new UserNotFoundException('No valid user was found', $e->getCode(), $e->getPrevious());
    }
    catch (\Exception $e) {
      throw new UserNotFoundException('No valid user was found', 0, $e);
    }

    // Based on the current implementation, it's not possible for
    // `SimpleOauthAuthenticationProvider::authenticate` to return NULL
    // but in case the implementation changes, we're being defensive here.
    if (!$account) {
      throw new UserNotFoundException('No valid user was found');
    }

    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager()->getStorage('user')->load($account->id());
    if (!$user || $user->isAnonymous() || $user->isBlocked()) {
      throw new UserNotFoundException('No valid user was found');
    }

    return $user;
  }

}
