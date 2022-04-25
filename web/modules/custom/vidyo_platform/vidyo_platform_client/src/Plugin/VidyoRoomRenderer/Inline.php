<?php

namespace Drupal\vidyo_platform_client\Plugin\VidyoRoomRenderer;

use Drupal\Core\Session\AccountInterface;
use Drupal\vidyo_platform\Api\Model\Room;
use Drupal\vidyo_platform\Plugin\VidyoRoomRendererBase;

/**
 * Renders the Vidyo room inline.
 *
 * @VidyoRoomRenderer(
 *  id = "inline",
 *  label = @Translation("Inline"),
 *  description = @Translation("Renders the Vidyo Room in the current context")
 * )
 */
class Inline extends VidyoRoomRendererBase {

  /**
   * {@inheritdoc}
   */
  public function renderRoom(Room $room, AccountInterface $account): array {
    return [
      '#theme' => 'vidyo_client',
      '#room' => $room,
      '#room_host' => $room->getHost(),
      '#room_key' => $room->getRoomKey(),
      '#room_extension' => $room->getExtension(),
      '#room_pin' => $room->getPin(),
      '#attendee_name' => $account->getDisplayName(),
      '#attached' => [
        'library' => ['vidyo_platform_client/js-client'],
      ],
    ];
  }

}
