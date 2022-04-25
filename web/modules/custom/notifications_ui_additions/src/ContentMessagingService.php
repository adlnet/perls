<?php

namespace Drupal\notifications_ui_additions;

use Drupal\file\FileInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Entity\EntityInterface;
use Drupal\firebase\FirebaseServiceInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\notifications\Entity\PushNotification;
use Psr\Log\LoggerInterface;

/**
 * Convenience methods for adding related content to a notification.
 */
class ContentMessagingService {

  /**
   * Drupal\firebase\FirebaseServiceInterface definition.
   *
   * @var \Drupal\firebase\FirebaseServiceInterface
   */
  protected $firebaseMessagingService;

  /**
   * Drupal\monolog\Logger\Logger definition.
   *
   * @var \Drupal\monolog\Logger\Logger
   */
  protected $logger;

  /**
   * Drupal\Core\Image\ImageFactory definition.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Constructs a new ContentMessagingService object.
   */
  public function __construct(FirebaseServiceInterface $notifications_firebase_message, LoggerInterface $logger_channel_default, ImageFactory $image_factory) {
    $this->firebaseMessagingService = $notifications_firebase_message;
    $this->logger = $logger_channel_default;
    $this->imageFactory = $image_factory;
  }

  /**
   * Adds a related item to a notification.
   *
   * When a user opens a notification, they are taken to the related item.
   *
   * @param \Drupal\notifications\Entity\PushNotification $message
   *   The push notification to attach the content entity to.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The related item.
   * @param string $action
   *   The action for the mobile app to take with the item.
   */
  public function addRelatedItem(PushNotification $message, EntityInterface $entity, string $action = 'view_item') {
    $contents = $message->getMessage();
    $contents['notification']['image'] = $this->getRelatedImageUri($entity);
    $contents['data'] = $this->prepareMessageData($action, $entity);
    $message->setMessage($contents);
    $message->save();
  }

  /**
   * Retrieves a related item from a push notification.
   *
   * @param \Drupal\notifications\Entity\PushNotification $message
   *   The push notification.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The related item, or null.
   */
  public function getRelatedItem(PushNotification $message): ?EntityInterface {
    $data = $message->getMessageData();
    if (!$data || !isset($data['item'])) {
      return NULL;
    }

    $item = $data['item'];

    try {
      $storage = \Drupal::entityTypeManager()->getStorage($item['type']);
    }
    catch (PluginNotFoundException $e) {
      return NULL;
    }

    // Older notifications don't have the ID property.
    if (isset($item['id'])) {
      $results = $storage->loadByProperties(['uuid' => $item['id']]);
      return reset($results) ?: NULL;
    }
    elseif (isset($item['url'])) {
      $id = basename($item['url']);
      return $storage->load($id);
    }

    return NULL;
  }

  /**
   * Prepares the message data for adding to the push notification.
   *
   * @param string $action
   *   The action.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The related content.
   * @param array $relatedContent
   *   Optionally, additional related content.
   *
   * @return array
   *   The data object to append to the notification.
   */
  public function prepareMessageData(string $action, EntityInterface $entity, array $relatedContent = []): array {
    $data = [
      'action' => $action,
      'item' => $this->prepareItem($entity),
    ];

    if (!empty($relatedContent)) {
      $data['related_items'] = array_map(function ($related_item) {
        return $this->prepareItem($related_item);
      }, $relatedContent);
    }

    return $data;
  }

  /**
   * Retrieves an image associated with the specified entity.
   *
   * This image is appropriate for showing alongside notifications.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A content entity.
   *
   * @return string|null
   *   An absolute URL to an image representing that entity.
   */
  protected function getRelatedImageUri(EntityInterface $entity): ?string {
    if ($entity->hasField('field_media_image')) {
      $media = $entity->field_media_image->entity;
    }
    elseif ($entity->hasField('field_artwork')) {
      $media = $entity->field_artwork->entity;
    }

    if (!isset($media)) {
      return NULL;
    }

    $file = $media->field_media_image->entity;

    if (!isset($file)) {
      return NULL;
    }

    return $this->prepareImageUri($file);
  }

  /**
   * Prepares an image derivative and returns the URL.
   *
   * @param \Drupal\file\FileInterface $file
   *   The image to create a derivative of.
   *
   * @return string|null
   *   The URL to the image derivative, or null.
   */
  protected function prepareImageUri(FileInterface $file): ?string {
    /** @var \Drupal\image\Entity\ImageStyle $style */
    $style = ImageStyle::load('mobile_200x200');
    $image_uri = $file->getFileUri();
    $style->createDerivative($image_uri, $style->buildUri($image_uri));
    return $style->buildUrl($image_uri);
  }

  /**
   * Prepare a single entity for a push notification payload.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The prepared item for the push notification payload.
   */
  protected function prepareItem(EntityInterface $entity): array {
    return [
      'type' => $entity->getEntityTypeId(),
      'id' => $entity->uuid(),
      'url' => $entity->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
  }

}
