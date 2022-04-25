<?php

declare(strict_types = 1);

namespace Drupal\business_rules_date_recur\EventSubscriber;

use Drupal\business_rules\Events\BusinessRulesEvent;
use Drupal\business_rules\Plugin\BusinessRulesReactsOnManager;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\date_recur_events\Event\EntityOccurrenceEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listening on Entity Occurrence events, dispatching Business Rules events.
 *
 * @package Drupal\business_rules_date_recur\EventSubscriber
 */
class BusinessRulesEventHandlerSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher definition.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * Business Rules Reacts On Manager.
   *
   * @var \Drupal\business_rules\Plugin\BusinessRulesReactsOnManager
   */
  protected $businessRulesReactsOnManager;

  /**
   * Constructs a new RulesEventHandlerSubscriber object.
   */
  public function __construct(
    ContainerAwareEventDispatcher $event_dispatcher,
    BusinessRulesReactsOnManager $reacts_on_manager
  ) {
    $this->eventDispatcher = $event_dispatcher;
    $this->businessRulesReactsOnManager = $reacts_on_manager;
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
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onEntityStarting(EntityOccurrenceEvent $event) {
    $this->dispatchBusinessRulesEvent($event, 'entity_is_starting');
  }

  /**
   * Invoked when an entity is ending.
   *
   * @param \Drupal\date_recur_events\Event\EntityOccurrenceEvent $event
   *   The dispatched event.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onEntityEnding(EntityOccurrenceEvent $event) {
    $this->dispatchBusinessRulesEvent($event, 'entity_is_ending');
  }

  /**
   * Dispatches a business rules event based on plugin id.
   *
   * @param \Drupal\date_recur_events\Event\EntityOccurrenceEvent $event
   *   The entity occurrence event.
   * @param string $pluginId
   *   The plugin id to get the definition for.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function dispatchBusinessRulesEvent(
    EntityOccurrenceEvent $event,
    string $pluginId
  ) {
    $reactsOnDefinition = $this
      ->businessRulesReactsOnManager
      ->getDefinition($pluginId);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $event->getEntity();
    $event = new BusinessRulesEvent($entity, [
      'entity_type_id' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'entity' => $entity,
      'entity_unchanged' => $entity->original,
      'reacts_on' => $reactsOnDefinition,
      'loop_control' => $entity->getEntityTypeId() . $entity->id(),
    ]);
    $this->eventDispatcher->dispatch($reactsOnDefinition['eventName'], $event);
  }

}
