<?php

namespace Drupal\externalauth_redirect\EventSubscriber;

use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Responds to system requests to see if they need to be redirected for SSO.
 */
class RequestRedirectSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Drupal\Core\Routing\CurrentRouteMatch definition.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Drupal\Core\PageCache\ResponsePolicy\KillSwitch definition.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $cacheKillSwitch;

  /**
   * Constructs a new RequestSubscriber object.
   */
  public function __construct(TranslationInterface $string_translation, CurrentRouteMatch $current_route_match, AccountProxyInterface $current_user, KillSwitch $kill_switch) {
    $this->stringTranslation = $string_translation;
    $this->routeMatch = $current_route_match;
    $this->currentUser = $current_user;
    $this->cacheKillSwitch = $kill_switch;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['kernel.request'] = ['onRequest'];
    return $events;
  }

  /**
   * Checks if the request needs to be redirected to the default SSO host.
   *
   * When accessing SSO from a non default host name this method will
   * redirect traffic to the default host for SSO.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The request event.
   */
  public function onRequest(Event $event) {
    if ($this->routeMatch->getRouteName() !== 'simplesamlphp_auth.saml_login' || $this->currentUser->isAuthenticated()) {
      return;
    }

    // Check if the environment variable for  SimpleSaml_Host is set.
    if (getenv('SIMPLESAML_HOST') === FALSE) {
      return;
    }
    $simplesaml_host = getenv('SIMPLESAML_HOST');
    $request = $event->getRequest();

    if ($simplesaml_host === $request->getHost()) {
      // Already using default host so nothing to do.
      return;
    }
    $request_uri = Url::fromUserInput($request->getRequestUri(), ['absolute' => TRUE])->toString();

    // Avoid any caching of the log in screen from here on out.
    $this->cacheKillSwitch->trigger();

    $request_uri = str_replace($request->getHost(), $simplesaml_host, $request_uri);

    $response = new TrustedRedirectResponse($request_uri, TrustedRedirectResponse::HTTP_FOUND);

    $event->setResponse($response);
  }

}
