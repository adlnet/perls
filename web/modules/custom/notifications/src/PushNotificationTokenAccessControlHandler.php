<?php

namespace Drupal\notifications;

use Drupal\consumers\AccessControlHandler;

/**
 * Access controller for the Push Notification Token entity.
 *
 * @see \Drupal\notifications\Entity\PushNotificationToken.
 */
class PushNotificationTokenAccessControlHandler extends AccessControlHandler {

  /**
   * Contains the entity name.
   *
   * @var string
   *   Entity name.
   */
  public static $name = 'push_notification_token';

}
