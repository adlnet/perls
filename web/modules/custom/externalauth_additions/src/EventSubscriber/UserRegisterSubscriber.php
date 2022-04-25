<?php

namespace Drupal\externalauth_additions\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Redirects the user to fill out their profile after registering via SSO.
 *
 * When a new user is created after authenticating from an external system,
 * the externalauth.register and externalauth.login event occur on the same
 * request. We intercept the response from that request to redirect the new
 * user to fill out their profile.
 */
class UserRegisterSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Whether the user should be redirected to finish filling out their profile.
   *
   * Evaluated just before the response is sent back to the user.
   *
   * @var bool
   */
  private $shouldRedirectToUserProfile;

  /**
   * Constructs a new UserRegisterSubscriber object.
   */
  public function __construct(TranslationInterface $string_translation, AccountProxyInterface $current_user) {
    $this->stringTranslation = $string_translation;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['externalauth.register'] = ['onExternalUserRegister'];
    $events['kernel.response'] = ['onResponse'];

    return $events;
  }

  /**
   * Invoked when the externalauth.register event is dispatched.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function onExternalUserRegister(Event $event) {
    $this->shouldRedirectToUserProfile = TRUE;
  }

  /**
   * Invoked when the kernel.response event is dispatched.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function onResponse(Event $event) {
    $response = $event->getResponse();

    if (!$this->shouldRedirectToUserProfile || $this->currentUser->isAnonymous()) {
      return;
    }

    $destination = Url::fromRoute('entity.user.edit_form', [
      'user' => $this->currentUser->id(),
    ], [
      'query' => [
        'destination' => $this->getOriginalDestination($response),
      ],
    ]);

    \Drupal::messenger()->addMessage($this->t("You're almost ready to go! Take a few moments to finish setting up your profile."));
    $response = new RedirectResponse($destination->toString());
    $event->setResponse($response);

    $this->shouldRedirectToUserProfile = FALSE;
  }

  /**
   * Retrieves the original destination where the user was about to go.
   *
   * After the user updates their profile, we want them to go back to
   * the original destination so they can continue using the app.
   *
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The intercepted response.
   *
   * @return string|null
   *   The local path of the original destination.
   *   Returns NULL if the destination was not local.
   */
  protected function getOriginalDestination(Response $response) {
    global $base_url;

    if (!($response instanceof RedirectResponse)) {
      return NULL;
    }

    // The target URL should be a full URL, but we need to parse that down
    // to just the path to supply to the `destination` query parameter.
    $target = $response->getTargetUrl();

    // First--verify that the target URL is part of this Drupal installation.
    // If not, we can't redirect to it after the user fills out their profile.
    try {
      if (!UrlHelper::externalIsLocal($target, $base_url)) {
        return NULL;
      }
    }
    catch (\InvalidArgumentException $e) {
      // Although the target URL is almost _certainly_ a full URL,
      // it's _possible_ that it is a local path, which will trigger
      // an InvalidArgumentException.
      return $target;
    }

    // We've verified the target is on this Drupal install;
    // strip off the beginning of the target so we should be
    // left with the path, query, and fragment.
    return ltrim(substr($target, strlen($base_url)), '/');
  }

}
