<?php

namespace Drupal\vidyo_platform\Api\Model;

/**
 * Additional information for connecting to a Vidyo room.
 */
class RoomMode {
  /**
   * The room URL.
   *
   * @var string
   */
  protected $roomURL;
  /**
   * Whether the room is locked.
   *
   * @var bool
   */
  protected $isLocked;
  /**
   * Whether the room has an access PIN.
   *
   * @var bool
   */
  protected $hasPIN;
  /**
   * The access PIN for the room.
   *
   * @var string
   */
  protected $roomPIN;
  /**
   * Whether the room has a moderator PIN.
   *
   * @var bool
   */
  protected $hasModeratorPIN;
  /**
   * The moderator PIN for the room.
   *
   * @var string
   */
  protected $moderatorPIN;

  /**
   * Returns whether a PIN is required for accessing the room.
   *
   * @return bool
   *   TRUE if a PIN is required to access the room.
   */
  public function hasPin(): bool {
    return $this->hasPIN;
  }

  /**
   * Gets the access PIN for the room.
   *
   * @return string|null
   *   The access PIN.
   */
  public function getRoomPin(): ?string {
    return $this->roomPIN;
  }

  /**
   * Gets the URL for accessing the room.
   *
   * @return string|null
   *   The room URL.
   */
  public function getRoomUrl(): ?string {
    return $this->roomURL;
  }

  /**
   * Gets whether the room is currently locked.
   *
   * @return bool
   *   TRUE if the room is locked.
   */
  public function isLocked(): bool {
    return $this->isLocked;
  }

}
