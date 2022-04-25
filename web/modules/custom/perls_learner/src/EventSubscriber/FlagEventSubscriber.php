<?php

namespace Drupal\perls_learner\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\flag\Event\FlagEvents;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\Event\UnflaggingEvent;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listen to flagging event and invalidate the flagged entity cache.
 *
 * @package Drupal\perls_learner\EventSubscriber
 */
class FlagEventSubscriber implements EventSubscriberInterface {

  /**
   * Cache tag invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheInvalidator;

  /**
   * Invalidate the cache.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tag invalidator service.
   */
  public function __construct(CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->cacheInvalidator = $cache_tags_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[FlagEvents::ENTITY_FLAGGED][] = ['invalidateEntityCache', -100];
    $events[FlagEvents::ENTITY_UNFLAGGED][] = ['invalidateEntityCache', -100];
    return $events;
  }

  /**
   * Invalidate the entity cache after a flagging event.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The flagging or unflagging event.
   */
  public function invalidateEntityCache(Event $event) {
    if ($event instanceof FlaggingEvent) {
      $flagging = $event->getFlagging();
    }
    elseif ($event instanceof UnflaggingEvent) {
      /** @var \Drupal\flag\Entity\Flagging $flagging */
      $all_flagging = $event->getFlaggings();
      $flagging = reset($all_flagging);
    }
    $entity = $flagging->getFlaggable();
    $cache_tags = [
      "{$entity->getEntityTypeId()}:{$entity->id()}",
    ];

    $this->cacheInvalidator->invalidateTags($cache_tags);
  }

}
