<?php

namespace Drupal\user_email_access\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Displays a user's email address.
 *
 * Respects "view all email addresses" and "view own email address" permissions.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("user_email")
 */
class UserEmail extends FieldPluginBase {

  /**
   * No query required for user emaill adress.
   *
   * @{inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * Renders the user's email address from the user entity.
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $account = \Drupal::currentUser();
    $user = $values->_entity;
    if ($account->hasPermission('Administer users')
      || $account->hasPermission('view all email addresses')
      || ($account->hasPermission('view own email address') && $account->id() === $user->id())) {
      return $user->getEmail();
    }
    return NULL;
  }

}
