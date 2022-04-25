<?php

namespace Drupal\perls_group_management\Permissions;

/**
 * Provides mapping for group types permissions.
 */
abstract class GroupVisibilityPermission {

  public const PUBLIC_GROUP = 0;
  public const PRIVATE_GROUP = 1;

  /**
   * Returns an array of group type permissions.
   *
   * @return array
   *   The group type permissions.
   */
  public static function allCases() {
    $oClass = new \ReflectionClass(__CLASS__);
    return array_flip($oClass->getConstants());
  }

  /**
   * Returns the name of the group type permissions.
   *
   * @param string $key
   *   The key of the group type permission.
   *
   * @return string
   *   The name of the group type permissions.
   */
  public static function getTitle(string $key) {
    $key_replaced = str_replace("_", " ", $key);
    $title = ucwords(strtolower($key_replaced));
    return $title;
  }

}
