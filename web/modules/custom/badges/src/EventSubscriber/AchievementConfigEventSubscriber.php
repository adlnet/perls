<?php

namespace Drupal\badges\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\badges\Service\BadgeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A subscriber invalidating cache tags when color config objects are saved.
 */
class AchievementConfigEventSubscriber implements EventSubscriberInterface {

  /**
   * Badge plugin manager.
   *
   * @var \Drupal\badges\Service\BadgeService
   */
  protected $badgeService;

  /**
   * Constructs a AchievementConfigEventSubscriber object.
   *
   * @param \Drupal\badges\Service\BadgeService $badge_service
   *   Badge Service class.
   */
  public function __construct(BadgeService $badge_service) {
    $this->badgeService = $badge_service;
  }

  /**
   * Clean up achievements unlocked table when config is deleted.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The Event to process.
   */
  public function onDelete(ConfigCrudEvent $event) {
    // If config is deleted as part of a deploy we want to clean up
    // the unlocked table which keeps data from items that were not
    // removed by the UI.
    if (strpos($event->getConfig()->getName(), 'achievements.achievement_entity.') === 0) {
      $this->badgeService->cleanUnlockedData();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::DELETE][] = ['onDelete'];

    return $events;
  }

}
