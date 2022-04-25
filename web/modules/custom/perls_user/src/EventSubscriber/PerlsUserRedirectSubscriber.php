<?php

namespace Drupal\perls_user\EventSubscriber;

use Drupal\Core\Url;
use Drupal\perls_dashboard\Services\NewDashboardHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event Subscriber to redirect user page.
 */
class PerlsUserRedirectSubscriber implements EventSubscriberInterface {

  /**
   * Dashboard helper service.
   *
   * @var \Drupal\perls_dashboard\Services\NewDashboardHelper
   */
  protected $dashboardHelper;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return([
      KernelEvents::REQUEST => [
        ['redirectUserToUserEdit'],
        ['newDashboardActivated'],
      ],
    ]);
  }

  /**
   * Redirect requests for user/{user} to user/{user}/edit.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Drupal event.
   */
  public function redirectUserToUserEdit(RequestEvent $event) {
    $request = $event->getRequest();

    if ($request->attributes->get('_route') == 'entity.user.canonical') {

      $user = $request->attributes->get('user');
      $redirect_url = Url::fromRoute('entity.user.edit_form', ['user' => $user->id()]);
      ;
      $redirect_url->setRouteParameter('destination', '/');

      $response = new RedirectResponse($redirect_url->toString(), 301);
      $event->setResponse($response);
    }
  }

  /**
   * Set the dashboard helper service.
   *
   * @param \Drupal\perls_dashboard\Services\NewDashboardHelper $helper
   *   Dashboard helper.
   */
  public function setDashboardHelper(NewDashboardHelper $helper) {
    $this->dashboardHelper = $helper;
  }

  /**
   * Redirect user to new dashboard.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Request event.
   */
  public function newDashboardActivated(RequestEvent $event) {
    $request = $event->getRequest();
    if ($request->getPathInfo() === '/our_picks' && $this->dashboardHelper->isNewDashboardActive()) {
      $response = new RedirectResponse(Url::fromUserInput('/start')->toString(), 301);
      $event->setResponse($response);
    }
  }

}
