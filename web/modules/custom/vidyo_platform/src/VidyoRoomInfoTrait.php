<?php

namespace Drupal\vidyo_platform;

use Drupal\Core\Url;
use Drupal\vidyo_platform\Api\Model\Room;

/**
 * Convenience methods for handling room information.
 */
trait VidyoRoomInfoTrait {

  /**
   * Returns a render array for displaying a room's lock status.
   *
   * @param \Drupal\vidyo_platform\Api\Model\Room $room
   *   The room.
   * @param bool $include_toggle
   *   Whether to include a button for locking/unlocking the room.
   *
   * @return array
   *   The render array.
   */
  protected function renderRoomStatus(Room $room, bool $include_toggle = TRUE): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['vidyo-room-status-' . $room->getRoomKey()],
      ],
      'lock__status' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $room->isLocked() ? $this->t('Locked') : $this->t('Unlocked'),
      ],
      'lock__enable' => [
        '#type' => 'link',
        '#title' => $this->t('Lock'),
        '#access' => $room->isUnlocked() && $include_toggle,
        '#url' => Url::fromRoute('vidyo_platform.action_link_lock', ['room_key' => $room->getRoomKey()]),
        '#attributes' => [
          'class' => ['button', 'button--action', 'use-ajax'],
        ],
      ],
      'lock__disable' => [
        '#type' => 'link',
        '#title' => $this->t('Unlock'),
        '#access' => $room->isLocked() && $include_toggle,
        '#url' => Url::fromRoute('vidyo_platform.action_link_unlock', ['room_key' => $room->getRoomKey()]),
        '#attributes' => [
          'class' => ['button', 'button--action', 'use-ajax'],
        ],
      ],
    ];
  }

}
