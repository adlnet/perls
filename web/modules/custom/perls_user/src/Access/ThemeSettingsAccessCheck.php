<?php

namespace Drupal\perls_user\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Custom access to theme settings page.
 */
class ThemeSettingsAccessCheck {

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, RouteMatchInterface $route_match) {
    $themeName = $route_match->getParameter('theme');

    return AccessResult::allowedIfHasPermission($account, 'administer themes')
      ->orIf(AccessResult::allowedIf($account->hasPermission('administer perls theme') && $themeName === 'perls'));
  }

}
