<?php

namespace Drupal\xapi\Plugin\XapiActorIFI;

use Drupal\xapi\XapiActorIFIPluginBase;
use Drupal\user\UserInterface;

/**
 * This is an account IFI type where user doesn't have readable name.
 *
 * @XapiActorIFI(
 *   id = "email",
 *   label = @Translation("Email address"),
 *   description = @Translation("Here the actor is identified by email address.")
 * )
 */
class XapiMboxIFI extends XapiActorIFIPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getIfi(UserInterface $user = NULL) {
    if (!$user) {
      $user = $this->currentUser;
    }
    return ['mbox' => sprintf('mailto:%s', $user->getEmail())];
  }

  /**
   * {@inheritdoc}
   */
  public function isMyIfi($statement_actor) {
    return isset($statement_actor->mbox);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserFromActor($statement_actor) {
    $user = $this->userManager->loadByProperties([
      'mail' => str_replace('mailto:', '', $statement_actor->mbox),
    ]);

    if ($user) {
      return reset($user);
    }

    return NULL;
  }

}
