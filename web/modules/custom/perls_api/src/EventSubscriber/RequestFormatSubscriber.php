<?php

namespace Drupal\perls_api\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;

/**
 * Sets the proper request format.
 */
class RequestFormatSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST] = ['onRequest', 9999];
    return $events;
  }

  /**
   * Change request format to json if Accept header is set to application/json.
   *
   * @param Symfony\Component\EventDispatcher\Event $event
   *   The event to process.
   */
  public function onRequest(Event $event) {
    $request = $event->getRequest();
    // If a wrapper format is set for this request,
    // don't attempt to change the request format.
    if ($request->query->has(MainContentViewSubscriber::WRAPPER_FORMAT) || $request->getPathInfo() === "/views/ajax") {
      return;
    }
    // Assume the user wants JSON if the Accept header is application/json
    // or the path starts with /api/.
    $accept = $request->getAcceptableContentTypes();
    if (!empty($accept) && $accept[0] === 'application/json') {
      $request->setRequestFormat('json');
    }
    elseif (substr($request->getPathInfo(), 0, 5) === '/api/') {
      $request->setRequestFormat('json');
    }
  }

}
