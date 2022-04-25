<?php

namespace Drupal\xapi;

use Drupal\user\UserInterface;

/**
 * Defines an interface for Xapi actor type plugin.
 */
interface XapiActorIFIInterface {

  /**
   * Return the translated name of this plugin.
   */
  public function label();

  /**
   * Return the description of this plugin.
   */
  public function getDescription();

  /**
   * Gives back the IFI of actor.
   *
   * @param \Drupal\user\UserInterface|null $user
   *   Optional. A drupal user entity.
   *
   * @return array
   *   The structure of a valid IFI. See available options here
   *   https://learningpool.com/xapi-statement-101-actor/
   */
  public function getIfi(UserInterface $user = NULL);

  /**
   * Decides the actor in the statement match with own plugin type.
   *
   * @param object $statement_actor
   *   An stdClass which represent the statement actor.
   *
   * @return bool
   *   Decides that the object structure match with the plugin generated one.
   */
  public function isMyIfi($statement_actor);

  /**
   * Load the drupal user from actor.
   *
   * @param object $statement_actor
   *   An stdClass which represent the statement actor.
   *
   * @return \Drupal\user\UserInterface|null
   *   The drupal user.
   */
  public function getUserFromActor($statement_actor);

}
