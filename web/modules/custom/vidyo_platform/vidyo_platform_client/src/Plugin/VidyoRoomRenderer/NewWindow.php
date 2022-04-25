<?php

namespace Drupal\vidyo_platform_client\Plugin\VidyoRoomRenderer;

use Drupal\Core\Session\AccountInterface;
use Drupal\vidyo_platform\Api\Model\Room;

/**
 * Opens a new window for Vidyo.
 *
 * @VidyoRoomRenderer(
 *  id = "new_window",
 *  label = @Translation("New Window"),
 *  description = @Translation("Renders a link that opens the Vidyo Web client in a new window")
 * )
 */
class NewWindow extends VidyoRoomLinkBase {

  /**
   * {@inheritdoc}
   */
  protected function getLink(Room $room, AccountInterface $account): array {
    return [
      '#attributes' => [
        'target' => 'vidyo',
        'class' => ['button', 'button--primary', 'vidyo--launch'],
      ],
      '#attached' => [
        'library' => ['vidyo_platform_client/room-launcher'],
      ],
    ] + parent::getLink($room, $account);
  }

}
