<?php

namespace Drupal\notifications_ui_additions\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Subscriber for modifying action definitions.
 */
class ActionDefinitionEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['views_bulk_operations.action_definitions'] = ['onAlterDefinitionEvent'];
    return $events;
  }

  /**
   * Invoked when VBO prepares a list of action definitions.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function onAlterDefinitionEvent(Event $event) {
    foreach ($event->definitions as &$definition) {
      if ($definition['id'] === 'send_push_notification') {
        $definition['class'] = 'Drupal\notifications_ui_additions\Plugin\Action\SendNotification';
      }
    }
  }

}
