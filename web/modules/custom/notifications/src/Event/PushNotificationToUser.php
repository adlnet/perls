<?php

namespace Drupal\notifications\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a PushNotificationToUser event.
 */
class PushNotificationToUser extends Event {

  /**
   * PUSH_NOTIFICATION_TO_USER constant.
   */
  const PUSH_NOTIFICATION_TO_USER = 'push_notifications_user_notify_event';

  /**
   * Entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Constructs a entity insertion event object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being inserted.
   */
  public function __construct(EntityInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * Get the inserted entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The node inserted.
   */
  public function getEntity() {
    return $this->entity;
  }

}
