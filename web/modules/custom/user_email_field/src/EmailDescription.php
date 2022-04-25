<?php

namespace Drupal\user_email_field;

/**
 * Helper class to manage the email description.
 */
class EmailDescription {

  /**
   * Defauult email field description in core.
   */
  const DEFAULT_DESCRIPTION = "A valid email address. All emails from the system will be sent to this address. The email address is not made public and will only be used if you wish to receive a new password or wish to receive certain news or notifications by email.";

  /**
   * Gives back the configured email description.
   *
   * @return string
   *   The configured description.
   */
  public static function getEmailDescription() {
    $config = \Drupal::configFactory()->get('user.settings');
    // @codingStandardsIgnoreStart
    return t(($config->get('email_description') ?? self::DEFAULT_DESCRIPTION));
    // @codingStandardsIgnoreEnd
  }

}
