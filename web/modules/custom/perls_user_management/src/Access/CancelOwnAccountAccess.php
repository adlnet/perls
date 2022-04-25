<?php

namespace Drupal\perls_user_management\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\perls_user_management\RegistrationAccessCheck;

/**
 * Checks access for cancelling own account if visitors can create new account.
 */
class CancelOwnAccountAccess implements AccessInterface {

  /**
   * Registration access check service.
   *
   * @var \Drupal\perls_user_management\RegistrationAccessCheck
   */
  private $registrationAccess;

  /**
   * Constructs a new CancelOwnAccountAccess object.
   *
   * @param \Drupal\perls_user_management\RegistrationAccessCheck $registration_access
   *   Config factory service.
   */
  public function __construct(RegistrationAccessCheck $registration_access) {
    $this->registrationAccess = $registration_access;
  }

  /**
   * A custom access check verified if users can cancel their own account.
   *
   * @param \Drupal\Core\Session\AccountProxy $account
   *   Account proxy object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountProxy $account, RouteMatchInterface $route_match) {
    if (!empty($profile = $route_match->getParameter('user'))) {
      if ($this->registrationAccess->isAccountCancelAllowed($profile)) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::neutral();
  }

}
