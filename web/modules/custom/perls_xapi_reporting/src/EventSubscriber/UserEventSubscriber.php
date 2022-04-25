<?php

namespace Drupal\perls_xapi_reporting\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\core_event_dispatcher\Event\Entity\EntityDeleteEvent;
use Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent;
use Drupal\core_event_dispatcher\Event\Entity\EntityUpdateEvent;
use Drupal\perls_xapi_reporting\PerlsXapiVerb;
use Drupal\perls_content\EntityUpdateChecker;
use Drupal\xapi\LRSRequestGenerator;
use Drupal\xapi_reporting\XapiStatementCreator;
use Drupal\user\UserInterface;
use Drupal\user_event_dispatcher\Event\User\UserLoginEvent;
use Drupal\user_event_dispatcher\Event\User\UserLogoutEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listens for user events to report to the LRS.
 */
class UserEventSubscriber extends BaseEntityCrudSubscriber {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Current request route.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRoute;

  /**
   * Service to check entity is modified.
   *
   * @var \Drupal\perls_content\EntityUpdateChecker
   */
  protected $entityUpdateChecker;

  /**
   * Constructs a new UserEventSubscriber object.
   */
  public function __construct(
    LRSRequestGenerator $request_generator,
    XapiStatementCreator $statement_creator,
    AccountProxyInterface $current_user,
    CurrentRouteMatch $current_route,
    EntityUpdateChecker $entity_update_checker) {
    parent::__construct($request_generator, $statement_creator);
    $this->currentUser = $current_user;
    $this->currentRoute = $current_route;
    $this->entityUpdateChecker = $entity_update_checker;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['hook_event_dispatcher.user.login'] = ['onUserLogIn'];
    $events['hook_event_dispatcher.user.logout'] = ['onUserLogOut'];
    $events[KernelEvents::REQUEST][] = ['tokenRevokeCalled', 31];

    return $events + parent::getSubscribedEvents();
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsEntity(EntityInterface $entity): bool {
    return $entity instanceof UserInterface;
  }

  /**
   * {@inheritdoc}
   */
  protected function onEntityInserted(EntityInsertEvent $event) {
    $statement = $this->createStatement($event->getEntity())
      ->setVerb(PerlsXapiVerb::create());
    // If no session exists then user is self registering.
    if (!$this->currentUser->isAuthenticated()) {
      $statement->setActor($event->getEntity());
    }
    // Send xapi statement as new user if no session exists.
    $uid = !$this->currentUser->isAuthenticated() ? $event->getEntity()->id() : NULL;
    $this->sendStatement($statement, $uid);
  }

  /**
   * {@inheritdoc}
   */
  protected function onEntityUpdated(EntityUpdateEvent $event) {
    /** @var Drupal\user\UserInterface $user */
    $user = $event->getEntity();

    // Skip EntityUpdated statement if the user is new, or there is no change
    // in the user profile.
    if (!$this->currentUser->isAuthenticated() || !$this->entityUpdateChecker->isAltered($user)) {
      return;
    }

    $statements = [];

    $statements[] = $this->createStatement($event->getEntity())
      ->setVerb(PerlsXapiVerb::update());

    if ($user->original->isBlocked() && $user->isActive()) {
      $statements[] = $this->createStatement()
        ->setVerb(PerlsXapiVerb::approve())
        ->setObject($event->getEntity());
    }
    elseif ($user->original->isActive() && $user->isBlocked()) {
      $statements[] = $this->createStatement()
        ->setVerb(PerlsXapiVerb::deny())
        ->setObject($event->getEntity());
    }
    // Send xapi statement as new user if no session exists.
    $uid = !$this->currentUser->isAuthenticated() ? $event->getEntity()->id() : NULL;
    $this->sendStatements($statements, $uid);
  }

  /**
   * {@inheritdoc}
   */
  protected function onEntityDeleted(EntityDeleteEvent $event) {
    $statement = $this->createStatement($event->getEntity())
      ->setVerb(PerlsXapiVerb::delete());

    $uid = NULL;
    // If there is no current authenticated user,
    // then we'll attribute the action to the system.
    if (!$this->currentUser->isAuthenticated()) {
      $statement->setActorToSystem();
      $uid = 1;
    }

    $this->sendStatement($statement, $uid);
  }

  /**
   * Invoked when the user logs in.
   *
   * @param \Drupal\user_event_dispatcher\Event\User\UserLoginEvent $event
   *   The dispatched event.
   */
  public function onUserLogIn(UserLoginEvent $event) {
    $login_routes = ['user.login', 'simplesamlphp_auth.saml_login'];
    if (in_array($this->currentRoute->getRouteName(), $login_routes)) {
      $statement = $this->createStatement()
        ->setVerb(PerlsXapiVerb::loggedIn())
        ->setActivityToSystem();

      $this->sendStatement($statement);
    }
  }

  /**
   * Invoked when the user logs out.
   *
   * @param \Drupal\user_event_dispatcher\Event\User\UserLogoutEvent $event
   *   The dispatched event.
   */
  public function onUserLogOut(UserLogoutEvent $event) {
    $this->sendLogoutStatement();
  }

  /**
   * Catch the token revoke path the send logout XAPI statement.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Drupal kernel event.
   */
  public function tokenRevokeCalled(RequestEvent $event) {
    $route = $event->getRequest()->get('_route');
    if ($route === 'simple_oauth_revoke.revoke' && $this->currentUser->isAuthenticated()) {
      $this->sendLogoutStatement();
    }
  }

  /**
   * Sends a logout XAPI statement.
   */
  protected function sendLogoutStatement() {
    $statement = $this->createStatement()
      ->setVerb(PerlsXapiVerb::loggedOut())
      ->setActivityToSystem();

    $this->sendStatement($statement);
  }

}
