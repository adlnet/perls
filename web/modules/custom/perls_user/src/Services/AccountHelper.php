<?php

namespace Drupal\perls_user\Services;

use Drupal\externalauth\AuthmapInterface;
use Drupal\user\UserInterface;

/**
 * Helper methods to manage the user's account.
 */
class AccountHelper {

  /**
   * Authmap service.
   *
   * @var \Drupal\externalauth\AuthmapInterface
   */
  protected $authMap;

  /**
   * UserAccountHelper constructor.
   *
   * @param \Drupal\externalauth\AuthmapInterface $authmap
   *   The authmap service.
   */
  public function __construct(AuthmapInterface $authmap) {
    $this->authMap = $authmap;
  }

  /**
   * Checks that user needs to update own username.
   *
   * Currently the user name is automatically generated based on email address.
   * The users have option to change own email address, in this case we need to
   * change the username as well.
   *
   * @return bool
   *   TRUE if user needs to set username otherwise FALSE.
   */
  public function isUserNeedChangeUsername(UserInterface $user): bool {
    return isset($user->original) &&
      $user->original->getAccountName() === $user->original->getEmail() &&
      $user->original->getEmail() !== $user->getEmail() &&
      empty($this->authMap->get($user->id(), 'simplesamlphp_auth'));
  }

}
