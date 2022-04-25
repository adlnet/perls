<?php

namespace Drupal\notifications_goals;

use Drupal\user\UserInterface;

/**
 * Help to manage the notification times in different time zones.
 */
class UserNotificationTimeConverter {

  /**
   * This the 24h in seconds.
   */
  const MAX = 86400;

  const MIN = 0;

  /**
   * Calculates the time diff between site time zone and user time zone.
   *
   * @param \Drupal\user\UserInterface $user
   *   A Drupal user.
   *
   * @return int
   *   The diff between the site time and user time in seconds.
   */
  public static function getSiteTimeDiff(UserInterface $user) {
    $diff =& drupal_static(__FUNCTION__);
    if (!isset($diff)) {
      $user_timezone = $user->get('timezone')->getString();
      $site_default_timezone = \Drupal::config('system.date')->get('timezone.default');
      $user_time = new \DateTime('now', new \DateTimeZone($user_timezone));
      $site_time = new \DateTime('now', new \DateTimeZone($site_default_timezone));
      $diff = ($site_time->getTimestamp() + $site_time->getOffset() - ($user_time->getTimestamp() + $user_time->getOffset()));
    }
    return $diff;
  }

  /**
   * Converts between user's timezone and site's timezone.
   *
   * @param int $user_time
   *   The user's notification time in seconds.
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   * @param bool $back
   *   Indicate the direction of conversion. Back means from site time to user.
   *
   * @return int
   *   The converted time in seconds.
   */
  public static function convertTime(int $user_time, UserInterface $user, $back = FALSE) {
    $diff = self::getSiteTimeDiff($user);
    $new_values = drupal_static(__FUNCTION__, []);
    if (!isset($new_values[$user_time])) {
      if ($back) {
        $diff = -1 * $diff;
      }
      if (($user_time + $diff) > self::MAX) {
        $new_values[$user_time] = ($user_time + $diff) - self::MAX;
      }
      elseif (($user_time + $diff) < 0) {
        $new_values[$user_time] = self::MAX + ($user_time + $diff);
      }
      else {
        $new_values[$user_time] = $user_time + $diff;
      }
    }
    return $new_values[$user_time];
  }

}
