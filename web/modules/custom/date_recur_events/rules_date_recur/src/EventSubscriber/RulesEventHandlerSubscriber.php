<?php

namespace Drupal\rules_date_recur\EventSubscriber;

use Drupal\rules\Event\EntityEvent;
use Drupal\date_recur_events\Event\EntityOccurrenceEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;

/**
 * Subscribes to EntityOccurrenceEvent's and dispatches events for Rules.
 */
class RulesEventHandlerSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher definition.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * Constructs a new RulesEventHandlerSubscriber object.
   */
  public function __construct(ContainerAwareEventDispatcher $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      EntityOccurrenceEvent::EVENT_NAME_STARTING => ['onEntityStarting'],
      EntityOccurrenceEvent::EVENT_NAME_ENDING => ['onEntityEnding'],
    ];
  }

  /**
   * Invoked when an entity is starting.
   *
   * @param \Drupal\date_recur_events\Event\EntityOccurrenceEvent $event
   *   The dispatched event.
   */
  public function onEntityStarting(EntityOccurrenceEvent $event) {
    $this->eventDispatcher->dispatch("rules_entity_starting:{$event->getEntity()->getEntityTypeId()}", $this->convertEvent($event));
  }

  /**
   * Invoked when an entity is ending.
   *
   * @param \Drupal\date_recur_events\Event\EntityOccurrenceEvent $event
   *   The dispatched event.
   */
  public function onEntityEnding(EntityOccurrenceEvent $event) {
    $this->eventDispatcher->dispatch("rules_entity_ending:{$event->getEntity()->getEntityTypeId()}", $this->convertEvent($event));
  }

  /**
   * Converts an EntityOccurrenceEvent into an EntityEvent.
   *
   * @param \Drupal\date_recur_events\Event\EntityOccurrenceEvent $event
   *   The original event.
   *
   * @return \Drupal\rules\Event\EntityEvent
   *   The converted event.
   */
  protected function convertEvent(EntityOccurrenceEvent $event): EntityEvent {
    $entity = $event->getEntity();
    $entity_type = $entity->getEntityTypeId();
    return new EntityEvent($entity, [
      $entity_type => $entity,
      'field' => $event->getField(),
      'start_date' => $event->getStartDate()->format('c'),
      'end_date' => $event->getEndDate()->format('c'),
    ]);
  }

}
