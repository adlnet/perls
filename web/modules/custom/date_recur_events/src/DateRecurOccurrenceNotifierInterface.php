<?php

namespace Drupal\date_recur_events;

use Drupal\date_recur\DateRange;

/**
 * Handles dispatching events when entity occurrences are starting or ending.
 */
interface DateRecurOccurrenceNotifierInterface {

  /**
   * Dispatch events about entities starting within a time frame.
   *
   * @param int $since
   *   The timestamp of the start of the time frame.
   * @param string $length
   *   A relative length of the time frame (using PHP relative date formats).
   *
   * @see https://www.php.net/manual/en/datetime.formats.relative.php
   */
  public function dispatchStartingEvents(int $since, string $length = 'now');

  /**
   * Dispatch events about entities ending within a time frame.
   *
   * @param int $since
   *   The timestamp of the start of the time frame.
   * @param string $length
   *   A relative length of the time frame (using PHP relative date formats).
   *
   * @see https://www.php.net/manual/en/datetime.formats.relative.php
   */
  public function dispatchEndingEvents(int $since, string $length = 'now');

  /**
   * Retrieve a list of entities starting withing a date range.
   *
   * @param \Drupal\date_recur\DateRange $range
   *   The date range.
   *
   * @return array
   *   The entities starting within the range.
   */
  public function getEntitiesStartingWithinRange(DateRange $range): array;

  /**
   * Retrieve a list of entities ending within a date range.
   *
   * @param \Drupal\date_recur\DateRange $range
   *   The date range.
   *
   * @return array
   *   The entities ending within the range.
   */
  public function getEntitiesEndingWithinRange(DateRange $range): array;

}
