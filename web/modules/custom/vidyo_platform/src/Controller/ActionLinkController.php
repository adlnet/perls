<?php

namespace Drupal\vidyo_platform\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\vidyo_platform\Api\Model\Room;
use Drupal\Core\Controller\ControllerBase;
use Drupal\vidyo_platform\VidyoRoomInfoTrait;
use Drupal\vidyo_platform\Api\VidyoApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AJAX callbacks to perform actions on a Vidyo room.
 */
class ActionLinkController extends ControllerBase {

  use VidyoRoomInfoTrait;

  /**
   * Drupal\monolog\Logger\Logger definition.
   *
   * @var \Drupal\monolog\Logger\Logger
   */
  protected $logger;

  /**
   * Drupal\vidyo_platform\VidyoRoomManager definition.
   *
   * @var \Drupal\vidyo_platform\VidyoRoomManager
   */
  protected $roomManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->logger = $container->get('logger.channel.vidyo_platform');
    $instance->roomManager = $container->get('vidyo_platform.rooms');
    return $instance;
  }

  /**
   * Lock a Vidyo room.
   */
  public function lock($room_key) {
    $response = new AjaxResponse();

    try {
      $room = $this->roomManager->getRoomByKey($room_key);
      $this->roomManager->lockRoom($room);
      $response->addCommand($this->updateRoomStatus($room));
    }
    catch (VidyoApiException $e) {
      $this->logger->warning('Unable to lock room %key: %error', [
        '%key' => $room_key,
        '%error' => $e->getMessage(),
      ]);

      $response->addCommand(new AlertCommand($this->t('This room cannot be locked right now.')));
    }

    return $response;
  }

  /**
   * Unlock a Vidyo room.
   */
  public function unlock($room_key) {
    $response = new AjaxResponse();

    try {
      $room = $this->roomManager->getRoomByKey($room_key);
      $this->roomManager->unlockRoom($room);
      $response->addCommand($this->updateRoomStatus($room));
    }
    catch (VidyoApiException $e) {
      $this->logger->warning('Unable to unlock room %key: %error', [
        '%key' => $room_key,
        '%error' => $e->getMessage(),
      ]);

      $response->addCommand(new AlertCommand($this->t('This room cannot be unlocked right now.')));
    }

    return $response;
  }

  /**
   * Prepares updated room status information to return in response.
   *
   * @param \Drupal\vidyo_platform\Api\Model\Room $room
   *   The room to update.
   *
   * @return \Drupal\Core\Ajax\CommandInterface
   *   An AJAX command for updating the page.
   */
  private function updateRoomStatus(Room $room): CommandInterface {
    $room = $this->roomManager->getRoomById($room->getId());
    return new ReplaceCommand('.vidyo-room-status-' . $room->getRoomKey(), $this->renderRoomStatus($room));
  }

}
