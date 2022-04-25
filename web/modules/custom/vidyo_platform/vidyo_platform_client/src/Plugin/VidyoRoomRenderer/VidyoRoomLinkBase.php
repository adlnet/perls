<?php

namespace Drupal\vidyo_platform_client\Plugin\VidyoRoomRenderer;

use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\vidyo_platform\Api\Model\Room;
use Drupal\vidyo_platform\Plugin\VidyoRoomRendererBase;

/**
 * Opens a new window for Vidyo.
 */
abstract class VidyoRoomLinkBase extends VidyoRoomRendererBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function renderRoom(Room $room, AccountInterface $account): array {
    return [
      '#type' => 'container',
      'link' => $this->getLink($room, $account),
    ];
  }

  /**
   * Gets the link to render for the Vidyo room.
   *
   * @param \Drupal\vidyo_platform\Api\Model\Room $room
   *   The room to render.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account accessing the room.
   *
   * @return array
   *   The link to the room.
   */
  protected function getLink(Room $room, AccountInterface $account): array {
    return [
      '#type' => 'link',
      '#title' => $this->getLinkTitle($room, $account),
      '#url' => $this->getLinkUrl($room, $account),
    ];
  }

  /**
   * Gets the link title.
   *
   * @param \Drupal\vidyo_platform\Api\Model\Room $room
   *   The room to render.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account accessing the room.
   *
   * @return string
   *   The link title.
   */
  protected function getLinkTitle(Room $room, AccountInterface $account): string {
    return $this->t('Join Room');
  }

  /**
   * Gets the link URL.
   *
   * @param \Drupal\vidyo_platform\Api\Model\Room $room
   *   The room to render.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account accessing the room.
   *
   * @return \Drupal\Core\Url
   *   The link URL.
   */
  protected function getLinkUrl(Room $room, AccountInterface $account): Url {
    return Url::fromRoute('vidyo_platform_client.room', ['room_key' => $room->getRoomKey()]);
  }

}
