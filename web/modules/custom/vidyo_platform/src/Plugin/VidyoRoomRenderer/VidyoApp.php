<?php

namespace Drupal\vidyo_platform\Plugin\VidyoRoomRenderer;

use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\vidyo_platform\Api\Model\Room;
use Drupal\vidyo_platform\Plugin\VidyoRoomRendererBase;

/**
 * Provides a renderer for users to use the native Vidyo app to access the room.
 *
 * @VidyoRoomRenderer(
 *  id = "vidyo_app",
 *  label = @Translation("Vidyo App"),
 *  description = @Translation("Directs users to use the native Vidyo app for joining the room")
 * )
 */
class VidyoApp extends VidyoRoomRendererBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function renderRoom(Room $room, AccountInterface $account): array {
    return [
      '#type' => 'container',
      'url' => [
        '#type' => 'item',
        '#title' => $this->t('Room URL'),
        '#markup' => $room->getUrl(),
      ],
      'pin' => [
        '#type' => 'item',
        '#title' => $this->t('PIN'),
        '#markup' => $room->getPin(),
      ],
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Join Room'),
        '#url' => Url::fromUri($room->getUrl(), ['attributes' => ['target' => '_blank']]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ],
    ];
  }

}
