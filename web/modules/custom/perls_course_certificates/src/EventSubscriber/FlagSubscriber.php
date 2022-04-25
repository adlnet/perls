<?php

namespace Drupal\perls_course_certificates\EventSubscriber;

use Drupal\flag\Event\FlagEvents;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\badges\Service\BadgeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Flag subscriber to award certificate on course completion.
 */
class FlagSubscriber implements EventSubscriberInterface {

  /**
   * Badge service.
   *
   * @var \Drupal\badges\Service\BadgeService
   */
  protected $badgeService;

  /**
   * Constructor.
   */
  public function __construct(BadgeService $badge_service) {
    $this->badgeService = $badge_service;
  }

  /**
   * This method gets called when an entity is flagged.
   */
  public function onFlag(FlaggingEvent $event) {
    $flagging = $event->getFlagging();
    if ($flagging->bundle() === 'completed' && $flagging->getFlaggable()->bundle() === 'course') {
      if ($badge_plugin = $this->badgeService->getBadgePlugin('course_completion_certificates')) {
        $badge_plugin->updateUserProgress($flagging->getOwner());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[FlagEvents::ENTITY_FLAGGED][] = ['onFlag'];
    return $events;
  }

}
