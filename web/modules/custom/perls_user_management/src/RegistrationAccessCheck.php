<?php

namespace Drupal\perls_user_management;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Service to check if user registrations are open for visitors.
 */
class RegistrationAccessCheck {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Current logged in drupal user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a RegistrationAccessCheck object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->config = $config_factory->get('user.settings');
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks if registrations are open for visitors.
   *
   * @param \Drupal\user\UserInterface $account
   *   User account being deleted.
   *
   * @return bool
   *   Returns TRUE if registrations are open for visitors, FALSE otherwise.
   */
  public function isAccountCancelAllowed(UserInterface $account) {
    // Change cancel account button access only if the user does not have
    // permission to delete account and registrations are open to visitors.
    if ($account->id() !== $this->currentUser->id()) {
      return $account->access('delete', $this->currentUser);
    }
    else {
      $registration_access = $this->config->get('register');
      $registration_options = [
        UserInterface::REGISTER_VISITORS,
        UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL,
      ];

      return in_array($registration_access, $registration_options, TRUE);
    }
  }

}
