<?php

namespace Drupal\vidyo_platform_client\Plugin\VidyoRoomRenderer;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;
use Drupal\vidyo_platform\Api\Model\Room;

/**
 * Opens a modal for Vidyo.
 *
 * @VidyoRoomRenderer(
 *  id = "modal",
 *  label = @Translation("Modal"),
 *  description = @Translation("Renders a link that opens the Vidyo Web client in a modal")
 * )
 */
class Modal extends VidyoRoomLinkBase {

  /**
   * {@inheritdoc}
   */
  protected function getLink(Room $room, AccountInterface $account): array {
    return [
      '#attributes' => [
        'class' => ['button', 'button--primary', 'use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 'calc(100% - 300px)',
        ]),
      ],
    ] + parent::getLink($room, $account);
  }

}
