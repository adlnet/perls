<?php

namespace Drupal\perls_podcast\EventSubscriber;

use Drupal\Core\Image\ImageFactory;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\Entity\Node;
use Drupal\perls_podcast\Event\PodcastUpdateEvent;
use Drupal\notifications\Service\ExtendedFirebaseMessageService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber which listen to podcast notification event.
 */
class PodcastNotificationSubscriber implements EventSubscriberInterface {

  /**
   * Flag manager service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagManager;

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
   * @param \Drupal\notifications\Service\ExtendedFirebaseMessageService $pushNotifications
   *   Push notifications service.
   * @param \Drupal\Core\Image\ImageFactory $imageFactory
   *   Image factory service.
   */
  public function __construct(FlagServiceInterface $flagManager, ExtendedFirebaseMessageService $pushNotifications, ImageFactory $imageFactory) {
    $this->flagManager = $flagManager;
    $this->pushNotifications = $pushNotifications;
    $this->imageFactory = $imageFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PodcastUpdateEvent::PERLS_PODCAST_UPDATE => 'notify',
    ];
  }

  /**
   * Reacts to notify event.
   */
  public function notify(PodcastUpdateEvent $event) {
    $node = $event->getNode();

    if ($node->bundle() !== 'podcast') {
      return;
    }
    if (!$node->isPublished()) {
      return;
    }
    // Don't do anything without episodes.
    if ($node->get('field_episodes')->isEmpty()) {
      return;
    }

    $flag = $this->flagManager->getFlagById('bookmark');
    $flaggings = $this->flagManager->getEntityFlaggings($flag, $node);

    // Nobody has bookmarked it so far so don't do anything.
    if (empty($flaggings)) {
      return;
    }

    // Original entity object.
    $original = $node->original;

    $original_episodes = $original->get('field_episodes')->getValue();
    $episodes = $node->get('field_episodes')->getValue();
    $original_episode_ids = array_column($original_episodes, 'target_id');
    $episode_ids = array_column($episodes, 'target_id');
    $diffs = array_diff($episode_ids, $original_episode_ids);

    if (empty($diffs)) {
      return;
    }

    // At this point we sure to send notifications.
    $title = 'New Episode of ' . $node->getTitle();

    // They attached more than one "podcast episode" at the same time.
    if (count($diffs) > 1) {
      foreach ($diffs as $episode_id) {
        $episode = Node::load($episode_id);
        $message = $episode->getTitle();
        $data = $this->prepareNotificationData($node, $episode);
        foreach ($flaggings as $flagging) {
          $recipient_id = (int) $flagging->get('uid')->getString();
          $this->pushNotifications->sendPushNotification($title, $message, $recipient_id, $data);
        }
      }
    }
    else {
      $episode = Node::load(reset($diffs));
      $message = $episode->getTitle();
      $data = $this->prepareNotificationData($node, $episode);
      foreach ($flaggings as $flagging) {
        $recipient_id = (int) $flagging->get('uid')->getString();
        $this->pushNotifications->sendPushNotification($title, $message, $recipient_id, $data);
      }
    }
  }

  /**
   * Prepares notification data.
   */
  protected function prepareNotificationData($podcast, $episode) {
    return \Drupal::service('notifications_ui_additions.default')->prepareMessageData('NewPodcastEpisodeAdded', $podcast, [$episode]);
  }

}
