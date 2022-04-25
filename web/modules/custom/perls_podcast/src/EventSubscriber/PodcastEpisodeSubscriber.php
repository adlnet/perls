<?php

namespace Drupal\perls_podcast\EventSubscriber;

use Drupal\file\Entity\File;
use Drupal\perls_podcast\Event\PodcastEpisodeEvent;
use JamesHeinrich\GetID3\GetID3;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber which listen to podcast episode insert event.
 */
class PodcastEpisodeSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PodcastEpisodeEvent::PERLS_PODCAST_INSERT_UPDATE => 'episodeInsertUpdate',
    ];
  }

  /**
   * Reacts to episode insert/update event.
   */
  public function episodeInsertUpdate(PodcastEpisodeEvent $event) {
    $node = $event->getNode();
    $audioFile = $node->get('field_audio_file')->getValue();
    $fileID = $audioFile[0]['target_id'];
    $file = File::load($fileID);
    $getID3 = new GetID3();
    $analyzedFile = $getID3->analyze($file->getFileUri());
    // Just to make sure we store integer value.
    $duration = (int) floor($analyzedFile['playtime_seconds']);
    $node->set('field_duration', $duration);
  }

}
