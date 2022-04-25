<?php

namespace Drupal\vidyo_platform;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\vidyo_platform\Api\Client\UserServiceClient;
use Drupal\vidyo_platform\Api\Model\Room;
use Drupal\vidyo_platform\Api\VidyoApiException;

/**
 * Convenience methods for managing (creating/accessing/updating) Vidyo rooms.
 */
class VidyoRoomManager {

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Vidyo Portal User API client.
   *
   * @var \Drupal\vidyo_platform\Api\Client\UserServiceClient
   */
  protected $userServiceClient;

  /**
   * Constructs a new VidyoRoomManager object.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Determines whether the API connection with Vidyo is configured.
   *
   * This does not test if the API credentials are working,
   * merely that a portal URL has been specified.
   *
   * @return bool
   *   TRUE if the Vidyo API connection has been configured.
   */
  public function isConfigured(): bool {
    $portal_url = $this->getConfig()->get('portal_url');
    return !empty($portal_url);
  }

  /**
   * Creates a new Vidyo Room.
   *
   * This is a persistent room that can be reused.
   *
   * @param string $name
   *   The display name of the room.
   * @param string|null $pin
   *   Optionally, an access pin to associate with the room.
   *   If left empty, a pin is generated automatically.
   * @param bool $locked
   *   Whether the room should start as locked (default is FALSE).
   * @param string|null $description
   *   An optional description for the room.
   *
   * @return \Drupal\vidyo_platform\Api\Model\Room
   *   The created Vidyo room.
   *
   * @throws \Drupal\vidyo_platform\VidyoPlatformException
   *   Thrown if the room was not created.
   */
  public function createRoom(string $name, ?string $pin = NULL, bool $locked = FALSE, ?string $description = NULL): Room {
    $result = $this->getClient()
      ->createPublicRoom([
        'displayName' => $name,
        'locked' => $locked,
        'setPIN' => $pin ?? static::generatePin(),
        'description' => $description,
      ]);

    return $this->getRoomById($result->roomID);
  }

  /**
   * Creates a new ad-hoc Vidyo room.
   *
   * The room created by this method is not persistent and is automatically
   * cleaned up after `$duration` days. Additionally, rooms created by this
   * method will not be returned when searching or listing all rooms--it can
   * only be referenced by room key.
   *
   * @param int $duration
   *   The number of days to keep the room.
   * @param bool $generatePin
   *   Whether to generate a PIN for this room (defaults to TRUE).
   *
   * @return \Drupal\vidyo_platform\Api\Model\Room
   *   The created Vidyo room.
   *
   * @throws \Drupal\vidyo_platform\Api\VidyoApiException
   *   Thrown if the room was not created.
   */
  public function createAdhocRoom(int $duration = 1, bool $generatePin = TRUE): Room {
    $result = $this->getClient()
      ->createScheduledRoom([
        'recurring' => $duration,
        'setPIN' => $generatePin,
      ]);

    return $this->getRoomByUrl($result->roomURL);
  }

  /**
   * Retrieves a room for a given ID.
   *
   * @param string $id
   *   The room ID.
   *
   * @return \Drupal\vidyo_platform\Api\Model\Room
   *   The Vidyo room for the specified ID.
   *
   * @throws \Drupal\vidyo_platform\Api\VidyoApiException
   *   Thrown if no room was found with the room ID.
   */
  public function getRoomById(string $id): Room {
    $result = $this->getClient()->getEntityByEntityId(['entityID' => $id]);
    if ($result->total === 0) {
      throw new VidyoApiException('No room found with ID ' . $id, NULL, 404);
    }
    return reset($result->Entity);
  }

  /**
   * Retrieves a room associated with a room URL.
   *
   * The last component of the room URL is the room key.
   *
   * @param string $roomUrl
   *   A room URL.
   *
   * @return \Drupal\vidyo_platform\Api\Model\Room
   *   The Vidyo room for the specified URL.
   *
   * @throws \Drupal\vidyo_platform\Api\VidyoApiException
   *   Thrown if no room was found with the room key in the specified URL.
   */
  public function getRoomByUrl(string $roomUrl): Room {
    $key = basename($roomUrl);
    return $this->getRoomByKey($key);
  }

  /**
   * Retrieves a room associated with a room key.
   *
   * The last component of hte room URL is the room key.
   *
   * @param string $key
   *   A room key.
   *
   * @return \Drupal\vidyo_platform\Api\Model\Room
   *   The Vidyo room for the specified room key.
   *
   * @throws \Drupal\vidyo_platform\Api\VidyoApiException
   *   Thrown if no room was found with the specified key.
   */
  public function getRoomByKey(string $key): Room {
    $room = $this->getClient()->getEntityByRoomKey(['roomKey' => $key])->Entity;

    if (!$room->isRoomAccessible()) {
      throw new VidyoApiException('Unable to access room with key ' . $key, NULL, 401);
    }

    return $room;
  }

  /**
   * Sets the moderator PIN on a room.
   *
   * @param \Drupal\vidyo_platform\Api\Model\Room $room
   *   The room.
   * @param string $pin
   *   A new moderator PIN.
   *
   * @throws \Drupal\vidyo_platform\Api\VidyoApiException
   *   Thrown if the moderator PIN could not be set.
   */
  public function setModeratorPin(Room $room, string $pin) {
    $this->getClient()->createModeratorPIN([
      'roomID' => $room->getId(),
      'PIN' => $pin,
    ]);
  }

  /**
   * Removes a moderator PIN on a room.
   *
   * @param \Drupal\vidyo_platform\Api\Model\Room $room
   *   The room.
   *
   * @throws \Drupal\vidyo_platform\Api\VidyoApiException
   *   Thrown if the moderator PIN could not be removed.
   */
  public function removeModeratorPin(Room $room) {
    $this->getClient()->removeModeratorPIN(['roomID' => $room->getId()]);
  }

  /**
   * Retrieves a URL for accessing the moderator controls on a room.
   *
   * The URL is generated with a token so it does not require any additional
   * authentication for accessing the controls.
   *
   * @param \Drupal\vidyo_platform\Api\Model\Room $room
   *   The room.
   *
   * @return string
   *   A URL for accessing the moderator controls.
   *
   * @throws \Drupal\vidyo_platform\Api\VidyoApiException
   *   Thrown if the moderator URL could not be generated/retrieved.
   */
  public function getModeratorUrl(Room $room): string {
    return $this->getClient()->getModeratorURLWithToken(['roomID' => $room->getId()])->moderatorURL;
  }

  /**
   * Disconnects all attendees and deletes a Vidyo room.
   *
   * @param \Drupal\vidyo_platform\Api\Model\Room $room
   *   The room to delete.
   *
   * @throws \Drupal\vidyo_platform\Api\VidyoApiException
   *   Thrown if the room was not deleted.
   */
  public function deleteRoom(Room $room) {
    $this->deleteRoomById($room->getId());
  }

  /**
   * Disconnects all attendees and deletes a Vidyo room.
   *
   * @param string $id
   *   The room ID of the room to delete.
   *
   * @throws \Drupal\vidyo_platform\Api\VidyoApiException
   *   Thrown if the room was not deleted.
   */
  public function deleteRoomById(string $id) {
    $this->getClient()->deleteRoom(['roomID' => $id]);
  }

  /**
   * Locks the room to prevent additional attendees from joining.
   *
   * @param \Drupal\vidyo_platform\Api\Model\Room $room
   *   The room to lock.
   */
  public function lockRoom(Room $room) {
    $this->getClient()->lockRoom(['roomID' => $room->getId()]);
  }

  /**
   * Unlocks a room so additional attendees can join.
   *
   * @param \Drupal\vidyo_platform\Api\Model\Room $room
   *   The room to unlock.
   */
  public function unlockRoom(Room $room) {
    $this->getClient()->unlockRoom(['roomID' => $room->getId()]);
  }

  /**
   * Retrieves a configured client for accessing the Vidyo User Service.
   *
   * @return \Drupal\vidyo_platform\Api\Client\UserServiceClient
   *   A client for making API calls.
   *
   * @throws \Drupal\vidyo_platform\Api\VidyoApiException
   *   Thrown if the client is not properly configured.
   */
  public function getClient(): UserServiceClient {
    if (!$this->userServiceClient) {
      if (!$this->isConfigured()) {
        throw new VidyoApiException('The VidyoPlatform API is not configured');
      }
      $config = $this->getConfig();
      $this->userServiceClient = new UserServiceClient($config->get('portal_url'), $config->get('portal_username'), $config->get('portal_password'));
    }

    return $this->userServiceClient;
  }

  /**
   * Retrieves the configuration for the Vidyo API connection.
   *
   * @return \Drupal\Core\Config\Config
   *   The Vidyo API config.
   */
  protected function getConfig(): Config {
    return $this->configFactory->get('vidyo_platform.settings');
  }

  /**
   * Generates a random PIN that can be used when creating a room.
   *
   * @return string
   *   A 6-digit number.
   */
  private static function generatePin(): string {
    return '' . rand(100000, 999999);
  }

}
