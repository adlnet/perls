<?php

namespace Drupal\simple_oauth_revoke\Controller;

use Defuse\Crypto\Core;
use Defuse\Crypto\Crypto;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the Oauth2Revoke class to allow users to fully logout.
 */
class Oauth2Revoke extends ControllerBase {
  /**
   * The access token repository.
   *
   * @var \League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface
   */
  protected $accessTokenRepository;

  /**
   * The refresh token repository.
   *
   * @var \League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface
   */
  protected $refreshTokenRepository;

  /**
   * The encryption key.
   *
   * @var string
   */
  protected $encryptionKey;

  /**
   * Oauth2Revoke constructor.
   *
   * @param \League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface $access_token_repository
   *   The access token repository.
   * @param \League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface $refresh_token_repository
   *   The refresh token repository.
   */
  public function __construct(
    AccessTokenRepositoryInterface $access_token_repository,
    RefreshTokenRepositoryInterface $refresh_token_repository
    ) {
    $this->accessTokenRepository = $access_token_repository;
    $this->refreshTokenRepository = $refresh_token_repository;
    $salt = Settings::getHashSalt();
    if (Core::ourStrlen($salt) < 32) {
      throw OAuthServerException::serverError('Hash salt must be at least 32 characters long.');
    }
    $this->encryptionKey = Core::ourSubstr($salt, 0, 32);

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simple_oauth.repositories.access_token'),
      $container->get('simple_oauth.repositories.refresh_token')
    );
  }

  /**
   * Processes POST requests to /oauth/revoke.
   *
   * @param \Psr\Http\Message\ServerRequestInterface $request
   *   The revoke request object.
   */
  public function revoke(ServerRequestInterface $request) {
    // Extract the grant type from the request body.
    $body = $request->getParsedBody();

    // Get the auth server object from that uses the League library.
    try {
      $response = $this->revokeToken($body);
    }
    catch (OAuthServerException $exception) {
      watchdog_exception('simple_oauth_revoke', $exception);
      $response = $exception->generateHttpResponse(new Response());
    }
    return $response;
  }

  /**
   * A method to revoke the supllied tokens.
   *
   * @param array $request_parameters
   *   The parameters from the request.
   */
  protected function revokeToken(array $request_parameters) {
    $response = new Response();

    // Check that all parameters are set and
    // throw an error is something is missing.
    if (!isset($request_parameters['token'])) {
      throw OAuthServerException::invalidRequest('token');
    }
    if (!isset($request_parameters['client_id'])) {
      throw OAuthServerException::invalidRequest('client_id');
    }
    if (!isset($request_parameters['client_secret'])) {
      throw OAuthServerException::invalidRequest('client_secret');
    }
    $encryptedToken = $request_parameters['token'];
    // The original endpoint allowed both access and refresh tokens to be
    // revoked but this is not necessary as revoking the refresh can revoke
    // the access at the same time. Therefore if 'token_type_hint' is access
    // we just ignore the request and wait for the refresh revoke.
    // If no tip is set we default to refresh.
    if (!isset($request_parameters['token_type_hint']) || $request_parameters['token_type_hint'] != 'refresh_token') {
      return $response;
    }
    try {
      $decrypted_token = Crypto::decryptWithPassword($encryptedToken, $this->encryptionKey);
    }
    catch (\Throwable $t) {
      throw OAuthServerException::invalidRequest('token');
    }

    if (is_null($decrypted_token)) {
      throw OAuthServerException::invalidRequest('token');
    }

    $refresh_token = json_decode($decrypted_token, TRUE);
    // We do a check for client id to make sure we are revoking the correct
    // tokens. This probably isn't needed but added to ensure we don't
    // revoke tokens by accident.
    if (isset($refresh_token['client_id']) && $refresh_token['client_id'] == $request_parameters['client_id']) {
      if (isset($refresh_token['refresh_token_id'])) {
        $this->refreshTokenRepository->revokeRefreshToken($refresh_token['refresh_token_id']);
      }
      if (isset($refresh_token['access_token_id'])) {
        $this->accessTokenRepository->revokeAccessToken($refresh_token['access_token_id']);
      }
    }

    return $response;
  }

}
