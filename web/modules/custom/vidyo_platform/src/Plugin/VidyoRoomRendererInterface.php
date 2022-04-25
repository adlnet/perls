<?php

namespace Drupal\vidyo_platform\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\vidyo_platform\Api\Model\Room;

/**
 * Defines an interface for Vidyo Room Renderer plugins.
 */
interface VidyoRoomRendererInterface extends PluginInspectionInterface {

  /**
   * Renders the Vidyo room.
   *
   * @param \Drupal\vidyo_platform\Api\Model\Room $room
   *   The room to render.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account accessing the room.
   *
   * @return array
   *   The render array.
   */
  public function renderRoom(Room $room, AccountInterface $account): array;

}
