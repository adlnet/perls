<?php

namespace Drupal\perls_content_management\EventSubscriber;

use Drupal\entity_packager\Event\EntityPrePackageEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber which set event for packaking.
 *
 * @package Drupal\perls_content_management\EventSubscriber
 */
class ContentPackager implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[EntityPrePackageEvent::EVENT_ID][] = ['blockPackaging'];
    return $events;
  }

  /**
   * Prevent the packaging at nodes which has youtube videos.
   *
   * @param \Drupal\entity_packager\Event\EntityPrePackageEvent $event
   *   The event which triggered just before node packaging.
   */
  public function blockPackaging(EntityPrePackageEvent $event) {
    $entity = $event->getEntity();
    if ($entity->hasField('field_body')) {
      $sub_fields = $entity->get('field_body')->referencedEntities();
      /** @var \Drupal\paragraphs\Entity\Paragraph $field */
      foreach ($sub_fields as $field) {
        if ($field->bundle() === 'video' &&
        $field->hasField('field_video') &&
        !empty($field->get('field_video')->getString())) {
          $event->setPackaging(FALSE);
          return;
        }
      }

    }
  }

}
