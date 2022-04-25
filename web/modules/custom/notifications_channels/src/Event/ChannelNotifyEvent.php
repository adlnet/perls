<?php

namespace Drupal\notifications_channels\Event;

use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Provides a ChannelNotifyEvent event.
 */
class ChannelNotifyEvent extends Event {

  /**
   * NOTIFICATIONS_CHANNELS_NOTIFY_EVENT constant.
   */
  const NOTIFICATIONS_CHANNELS_NOTIFY_EVENT = 'notifications_channels_notify_event';


  /**
   * Node object.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * ChannelNotifyEvent constructor.
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
