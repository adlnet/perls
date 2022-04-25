<?php

namespace Drupal\bulk_user_upload\Event;

use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event that is fired when a user logs in.
 */
class UserImportEvent extends Event {

  const EVENT_NAME = 'bulk_user_upload_user_import';

  /**
   * The user account.
   *
   * @var \Drupal\user\UserInterface
   */
  public $user;

  /**
   * Current user row.
   *
   * @var array
   */
  public $importUserRow;

  /**
   * Constructs the object.
   *
   * @param \Drupal\user\UserInterface $user
   *   Newly imported user entity.
   * @param array $importUserRow
   *   Current user row.
   */
  public function __construct(UserInterface $user, array $importUserRow) {
    $this->user = $user;
    $this->importUserRow = $importUserRow;
  }

}
