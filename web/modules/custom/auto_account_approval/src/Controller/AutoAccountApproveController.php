<?php

namespace Drupal\auto_account_approval\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helper function to support the module's routes.
 */
class AutoAccountApproveController implements ContainerInjectionInterface {

  /**
   * Account related settings which are applied for all user.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $userSettings;

  /**
   * AutoAccountApproveController constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->userSettings = $config_factory->get('user.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Access check for settings page which appears on Dashboard.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   If $condition is TRUE, isAllowed() will be TRUE, otherwise isNeutral()
   *   will be TRUE.
   */
  public function configFormAccess(AccountInterface $account) {
    return AccessResult::allowedIf($this->userSettings->get('register') === UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL &&
    $account->hasPermission('administer auto account approval'));
  }

}
