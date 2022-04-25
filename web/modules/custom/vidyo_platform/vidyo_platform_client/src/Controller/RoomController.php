<?php

namespace Drupal\vidyo_platform_client\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\vidyo_platform\VidyoPlatformException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vidyo Room controller.
 */
class RoomController extends ControllerBase {

  /**
   * Drupal\vidyo_platform\VidyoRoomManager definition.
   *
   * @var \Drupal\vidyo_platform\VidyoRoomManager
   */
  protected $roomManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->roomManager = $container->get('vidyo_platform.rooms');
    $instance->currentUser = $container->get('current_user');
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * View a room.
   *
   * @param string $room_key
   *   The room key.
   */
  public function view(string $room_key) {
    $room = NULL;
    try {
      $room = $this->roomManager->getRoomByKey($room_key);
    }
    catch (VidyoPlatformException $e) {
      throw new NotFoundHttpException($e->getMessage(), $e);
    }

    $request = $this->requestStack->getCurrentRequest();
    $base_url = $request->getBaseUrl();
    $image_path = $base_url . '/' . drupal_get_path('module', 'vidyo_platform_client') . '/images';

    return [
      '#theme' => 'vidyo_client',
      '#image_path' => $image_path,
      '#room' => $room,
      '#room_host' => $room->getHost(),
      '#room_key' => $room->getRoomKey(),
      '#room_extension' => $room->getExtension(),
      '#room_pin' => $room->getPin(),
      '#attendee_name' => $this->currentUser()->getDisplayName(),
      '#attached' => [
        'library' => ['vidyo_platform_client/js-client'],
      ],
    ];
  }

}
