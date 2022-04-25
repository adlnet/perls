<?php

namespace Drupal\perls_podcast\Event;

use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Provides a PodcastEpisodeEvent event.
 */
class PodcastEpisodeEvent extends Event {

  /**
   * PERLS_PODCAST_INSERT_UPDATE constant.
   */
  const PERLS_PODCAST_INSERT_UPDATE = 'perls_podcast_insert_update';


  /**
   * Node object.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * PodcastEpisodeEvent constructor.
   */
  public function __construct(NodeInterface $node) {
    $this->node = $node;
  }

  /**
   * Node object.
   */
  public function getNode() {
    return $this->node;
  }

}
