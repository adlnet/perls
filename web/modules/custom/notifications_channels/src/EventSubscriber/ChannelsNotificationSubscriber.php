<?php

namespace Drupal\notifications_channels\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\flag\FlagServiceInterface;
use Drupal\notifications_channels\Event\ChannelNotifyEvent;
use Drupal\notifications\Service\ExtendedFirebaseMessageService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber which listen to podcast notification event.
 */
class ChannelsNotificationSubscriber implements EventSubscriberInterface {

  /**
   * Flag manager service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagManager;

  /**
   * The entity type manager Service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Push notifications service.
   *
   * @var \Drupal\notifications\Service\ExtendedFirebaseMessageService
   */
  protected $pushNotifications;

  /**
   * Image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * PodcastNotificationSubscriber constructor.
   *
   * @param \Drupal\flag\FlagServiceInterface $flagManager
   *   Flag manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type mangaer interface.
   * @param \Drupal\notifications\Service\ExtendedFirebaseMessageService $pushNotifications
   *   Push notifications service.
   * @param \Drupal\Core\Image\ImageFactory $imageFactory
   *   Image factory service.
   */
  public function __construct(FlagServiceInterface $flagManager, EntityTypeManagerInterface $entityTypeManager, ExtendedFirebaseMessageService $pushNotifications, ImageFactory $imageFactory) {
    $this->flagManager = $flagManager;
    $this->pushNotifications = $pushNotifications;
    $this->imageFactory = $imageFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ChannelNotifyEvent::NOTIFICATIONS_CHANNELS_NOTIFY_EVENT => 'notify',
    ];
  }

  /**
   * Reacts to notify event.
   */
  public function notify(ChannelNotifyEvent $event) {
    $node = $event->getNode();

    if (!$node->hasField('field_tags')) {
      return;
    }
    if (!$node->isPublished()) {
      return;
    }
    // Get all the referenced tags.
    $tags = $node->field_tags->referencedEntities();
    if (empty($tags)) {
      return;
    }

    $flag = $this->flagManager->getFlagById('following');

    // We want to build a list of users.
    // We only want to include each one once and want to
    // and we want to ensure that user has access.
    $user_notified = [];
    foreach ($tags as $tag) {
      $title = t('New content in #@tag', ['@tag' => $tag->label()]);
      $data = \Drupal::service('notifications_ui_additions.default')->prepareMessageData('new_content', $node, [$tag]);
      $flaggings = $this->flagManager->getEntityFlaggings($flag, $tag);
      foreach ($flaggings as $flagging) {
        $user = $flagging->getOwner();
        if (!in_array($user->id(), $user_notified) && $node->access('view', $user)) {
          // We are ready to send the notification.
          $this->pushNotifications->sendPushNotification($title, $node->getTitle(), $user->id(), $data);
          $user_notified[] = $user->id();
        }
      }

    }
  }

}
