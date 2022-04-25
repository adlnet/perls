<?php

namespace Drupal\externalauth_additions;

use Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\user\UserInterface;

/**
 * Provides convenience methods for updating/retrieving current SSO config.
 *
 * The SSO configuration form is basically a "nice" front-end to the
 * configuration form provided by SimpleSAMLphp Auth.
 */
class SSOConfigurationManager {

  /**
   * Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager definition.
   *
   * @var \Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager
   */
  protected $simplesamlphpAuthManager;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new SSOConfigurationManager object.
   */
  public function __construct(SimplesamlphpAuthManager $simplesamlphp_auth_manager, ConfigFactoryInterface $config_factory) {
    $this->simplesamlphpAuthManager = $simplesamlphp_auth_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Updates the configuration to require single sign-on.
   *
   * The `autoredirect` configuration parameter is not normally part of
   * simplesamlphp_auth.settings--we're adding it.
   *
   * When SSO is required, we also proactively disable new user registration
   * since all users should be coming through the identity provider.
   *
   * @param bool $required
   *   Whether single sign-on should be required.
   */
  public function setRequired($required) {
    $config = $this->configFactory->getEditable('simplesamlphp_auth.settings');
    $config->set('autoredirect', $required);
    $config->save();

    // If SSO is required, we'll also disable user registerion
    // since all users should be coming via the identity provider.
    // If SSO is not required, we're intentionally not reverting
    // this setting--that is up to the admin to decide.
    if ($required) {
      $user_config = $this->configFactory->getEditable('user.settings');
      $user_config->set('register', UserInterface::REGISTER_ADMINISTRATORS_ONLY);
      $user_config->save();
    }
  }

  /**
   * Returns whether single sign-on is required.
   *
   * @return bool
   *   TRUE if SSO is required.
   */
  public function isRequired() {
    return $this->isEnabled() && $this->ssoConfig()->get('autoredirect');
  }

  /**
   * Returns whether single sign-on is enabled.
   *
   * @return bool
   *   TRUE if SSO is enabled.
   */
  public function isEnabled() {
    return $this->simplesamlphpAuthManager->isActivated();
  }

  /**
   * Retrieves the SimpleSAMLphp Auth config.
   */
  protected function ssoConfig() {
    return $this->configFactory->get('simplesamlphp_auth.settings');
  }

}
