<?php

namespace Drupal\xapi\Plugin\XapiActorIFI;

use Drupal\xapi\XapiActorIFIPluginBase;
use Drupal\user\UserInterface;

/**
 * This is an account IFI type where user doesn't have readable name.
 *
 * @XapiActorIFI(
 *   id = "account",
 *   label = @Translation("Account"),
 *   description = @Translation("Identifies actors using their system account.")
 * )
 */
class XapiUuidAccountIFI extends XapiActorIFIPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getIfi(UserInterface $user = NULL) {
    if (!$user) {
      $user = $this->currentUser;
    }

    global $base_url;
    return [
      'account' => [
        'name' => $user->uuid(),
        'homePage' => $base_url,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isMyIfi($statement_actor) {
    return isset($statement_actor->account) && isset($statement_actor->account->name);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserFromActor($statement_actor) {
    $user = $this->userManager->loadByProperties([
      'uuid' => $statement_actor->account->name,
    ]);

    if ($user) {
      return reset($user);
    }

    return NULL;
  }

}
