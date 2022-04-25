<?php

namespace Drupal\perls_xapi_reporting\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\core_event_dispatcher\Event\Entity\EntityDeleteEvent;
use Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent;
use Drupal\core_event_dispatcher\Event\Entity\EntityUpdateEvent;

/**
 * Base methods for responding to entity crud events.
 */
abstract class BaseEntityCrudSubscriber extends BaseSubscriber {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['hook_event_dispatcher.entity.insert'] = ['onEntityInsert'];
    $events['hook_event_dispatcher.entity.update'] = ['onEntityUpdate'];
    $events['hook_event_dispatcher.entity.delete'] = ['onEntityDelete'];

    return $events + parent::getSubscribedEvents();
  }

  /**
   * This method is called when an entity is inserted.
   *
   * @param \Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent $event
   *   The dispatched event.
   */
  public function onEntityInsert(EntityInsertEvent $event) {
    if ($this->supportsEntity($event->getEntity())) {
      $this->onEntityInserted($event);
    }
  }

  /**
   * This method is called when an entity is updated.
   *
   * @param \Drupal\core_event_dispatcher\Event\Entity\EntityUpdateEvent $event
   *   The dispatched event.
   */
  public function onEntityUpdate(EntityUpdateEvent $event) {
    if ($this->supportsEntity($event->getEntity())) {
      $this->onEntityUpdated($event);
    }
  }

  /**
   * This method is called when an entity is deleted.
   *
   * @param \Drupal\core_event_dispatcher\Event\Entity\EntityDeleteEvent $event
   *   The dispatched event.
   */
  public function onEntityDelete(EntityDeleteEvent $event) {
    if ($this->supportsEntity($event->getEntity())) {
      $this->onEntityDeleted($event);
    }
  }

  /**
   * Specify whether the subscriber supports the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that was either created, updated, or deleted.
   *
   * @return bool
   *   TRUE if the subscriber wants to report on it.
   */
  abstract protected function supportsEntity(EntityInterface $entity): bool;

  /**
   * Invoked when a supported entity is inserted.
   *
   * @param \Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent $event
   *   The dispatched event.
   */
  protected function onEntityInserted(EntityInsertEvent $event) {}

  /**
   * Invoked when a supported entity is updated.
   *
   * @param \Drupal\core_event_dispatcher\Event\Entity\EntityUpdateEvent $event
   *   The dispatched event.
   */
  protected function onEntityUpdated(EntityUpdateEvent $event) {}

  /**
   * Invoked when a supported entity is deleted.
   *
   * @param \Drupal\core_event_dispatcher\Event\Entity\EntityDeleteEvent $event
   *   The dispatched event.
   */
  protected function onEntityDeleted(EntityDeleteEvent $event) {}

}
