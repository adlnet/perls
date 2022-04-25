<?php

namespace Drupal\vidyo_platform\Api\Model;

/**
 * A Vidyo room.
 */
class Room {
  /**
   * The room ID.
   *
   * @var string
   */
  protected $entityID;

  /**
   * The display name for this room.
   *
   * @var string
   */
  protected $displayName;

  /**
   * The room extension.
   *
   * @var string
   */
  protected $extension;

  /**
   * Additional information about the room.
   *
   * @var RoomMode
   */
  // @codingStandardsIgnoreLine
  protected $RoomMode;

  /**
   * The current room status.
   *
   * @var string
   */
  // @codingStandardsIgnoreLine
  protected $RoomStatus;

  /**
   * The ID of the room's owner.
   *
   * @var string
   */
  protected $ownerID;

  /**
   * Retrieves the room ID.
   *
   * @return string
   *   The room ID.
   */
  public function getId(): string {
    return $this->entityID;
  }

  /**
   * Gets the display name of the room.
   *
   * @return string
   *   The display name.
   */
  public function getDisplayName(): string {
    return $this->displayName;
  }

  /**
   * Retrieves the room key.
   *
   * @return string
   *   The room key.
   */
  public function getRoomKey(): string {
    return basename($this->getUrl());
  }

  /**
   * Retrieves the server host for this room.
   *
   * @return string
   *   The host.
   */
  public function getHost(): string {
    return parse_url($this->getUrl(), PHP_URL_HOST);
  }

  /**
   * Retrieves the URL for joining the room.
   *
   * @return string
   *   A URL for joining the room.
   */
  public function getUrl(): string {
    return $this->RoomMode->getRoomUrl() ?? '';
  }

  /**
   * Retrieves the room extension (used for dialing into the room).
   *
   * @return string
   *   The room extension.
   */
  public function getExtension(): string {
    return $this->extension;
  }

  /**
   * Retrieves the access PIN for the room (if one exists).
   *
   * @return string|null
   *   The room access PIN, or null if there is no access PIN.
   */
  public function getPin(): ?string {
    if (!$this->RoomMode->hasPin()) {
      return NULL;
    }

    return $this->RoomMode->getRoomPin();
  }

  /**
   * Determines if we're capable of accessing this room.
   *
   * The room could have been created by a different user on this account
   * and thus would be inaccessible.
   *
   * @return bool
   *   TRUE if the room is accessible.
   */
  public function isRoomAccessible(): bool {
    return !$this->RoomMode->hasPin() || $this->getPin() !== '****';
  }

  /**
   * Gets whether the room is currently unlocked.
   *
   * @return bool
   *   TRUE if the room is unlocked.
   */
  public function isUnlocked(): bool {
    return !$this->isLocked();
  }

  /**
   * Gets whether the room is currently locked.
   *
   * @return bool
   *   TRUE if the room is locked.
   */
  public function isLocked(): bool {
    return $this->RoomMode->isLocked();
  }

  /**
   * Determines whether the room currently has occupants.
   *
   * @return bool
   *   TRUE if the room has occupants.
   */
  public function hasOccupants(): bool {
    return $this->RoomStatus !== 'Empty';
  }

}
