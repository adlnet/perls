<?php

namespace Drupal\externalauth_additions\Plugin\XapiActorIFI;

use Drupal\xapi\Plugin\XapiActorIFI\XapiUuidAccountIFI;
use Drupal\user\UserInterface;

/**
 * This is an account IFI type for organizational users using single sign-on.
 *
 * @XapiActorIFI(
 *   id = "sso",
 *   label = @Translation("Organization Account"),
 *   description = @Translation("The actor is identified based on identifying information from the identity provider (single sign-on).")
 * )
 */
class XapiSSOAccountIFI extends XapiUuidAccountIFI {

  /**
   * {@inheritdoc}
   */
  public function getIfi(UserInterface $user = NULL) {
    if (!$user) {
      $user = $this->currentUser;
    }
    $id = $user->get('field_organization_identifier')->getString();
    $homepage = $user->get('field_organization_homepage')->getString();
    if (!empty($id) && !empty($homepage)) {
      return [
        'account' => [
          'name' => $id,
          'homePage' => $homepage,
        ],
      ];
    }

    // If organization identifier is empty, fallback to account type IFI plugin.
    return parent::getIfi($user);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserFromActor($statement_actor) {
    $user = $this->userManager->loadByProperties([
      'field_organization_identifier' => $statement_actor->account->name,
    ]);

    if ($user) {
      return reset($user);
    }
    elseif ($user = parent::getUserFromActor($statement_actor)) {
      // Fallback to account type plugin if organization_identifier is empty.
      return $user;
    }

    return NULL;
  }

}
