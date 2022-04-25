<?php

namespace Drupal\externalauth_additions\EventSubscriber;

use Drupal\externalauth\Event\ExternalAuthLoginEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\externalauth_additions\SSOConfigurationManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Responds to system requests to see if they need to be redirected for SSO.
 */
class RequestSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Drupal\externalauth_additions\SSOConfigurationManager definition.
   *
   * @var \Drupal\externalauth_additions\SSOConfigurationManager
   */
  protected $ssoConfiguration;

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
   * Drupal\Core\Messenger\MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new RequestSubscriber object.
   */
  public function __construct(TranslationInterface $string_translation, SSOConfigurationManager $sso_configuration, CurrentRouteMatch $current_route_match, AccountProxyInterface $current_user, KillSwitch $kill_switch, MessengerInterface $messenger) {
    $this->stringTranslation = $string_translation;
    $this->ssoConfiguration = $sso_configuration;
    $this->routeMatch = $current_route_match;
    $this->currentUser = $current_user;
    $this->cacheKillSwitch = $kill_switch;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['kernel.request'] = ['onRequest'];
    $events['externalauth.login'] = ['onExternalUserLogin'];

    return $events;
  }

  /**
   * Checks if the request needs to be redirected to the SSO identity provider.
   *
   * When SSO is required, redirect unauthenticated requests to the
   * log in screen to the identity provider. However, avoid redirecting if the
   * `local` query parameter is set on the request.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The request event.
   */
  public function onRequest(Event $event) {
    if ($this->routeMatch->getRouteName() !== 'user.login' || $this->currentUser->isAuthenticated() || !$this->ssoConfiguration->isRequired()) {
      return;
    }

    $request = $event->getRequest();

    // Allows us to still log in with a local account (for support purposes).
    if ($request->query->getBoolean('local') || $request->getMethod() === 'POST') {
      return;
    }

    // Avoid any caching of the log in screen from here on out.
    $this->cacheKillSwitch->trigger();

    // If there are error messages waiting for the user,
    // avoid automatically redirecting to the identity provider.
    // This is how we avoid an infinite redirect loop when the user's
    // account is blocked.
    if ($this->messengerHasErrors()) {
      return;
    }

    // Special case: avoid redirecting the reachability check made by the app.
    // The app periodically checks the server to see if it is reachable.
    // When "Require SSO" is enabled, it can send the app into a series
    // of endless redirects (which will cause the reachability check to fail).
    if ($request->getMethod() === 'HEAD' && $request->headers->get('accept') === '*/*') {
      return;
    }

    $saml_login_path = Url::fromRoute('simplesamlphp_auth.saml_login', [], [
      'query' => [
        // The SimpleSAMLphp Auth module uses the ReturnTo header to indicate
        // where SimpleSAMLphp should redirect after authenticating the user.
        'ReturnTo' => $request->getUri(),
      ],
    ]);

    // The original destination query param is now captured in the `ReturnTo`
    // value and can be removed from the original request
    // (so we can redirect to the IdP).
    $request->query->remove('destination');

    $response = new RedirectResponse($saml_login_path->toString(), RedirectResponse::HTTP_FOUND);
    $event->setResponse($response);
  }

  /**
   * Check for the log in of a blocked user and log an error.
   *
   * The presence of this error message prevents the system from going into
   * an infinite redirect loop between Drupal and the IdP.
   *
   * @param \Drupal\externalauth\Event\ExternalAuthLoginEvent $event
   *   The login event.
   */
  public function onExternalUserLogin(ExternalAuthLoginEvent $event) {
    if ($event->getAccount()->isBlocked()) {
      // We only need to log a message; Drupal takes care of preventing
      // the user from actually using the service.
      $this->messenger->addError($this->t('You successfully logged in, but you are not allowed to use this service.'));
    }
  }

  /**
   * Checks if there are errors waiting to be displayed to the user.
   *
   * @return bool
   *   TRUE if there are error messages waiting to be shown to the user.
   */
  protected function messengerHasErrors() {
    $errors = $this->messenger->messagesByType(MessengerInterface::TYPE_ERROR);
    return count($errors) > 0;
  }

}
