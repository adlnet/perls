<?php

namespace Drupal\monolog_extra\Monolog\Formatter;

use Monolog\Formatter\LineFormatter;

/**
 * Extends LineFomatter to shorten the backtrace information.
 */
class MinimalLineFormatter extends LineFormatter {

  /**
   * {@inheritDoc}
   */
  protected function convertToString($data): string {
    if (isset($data['backtrace']) && is_array($data['backtrace'])) {
      // If the data contains a backtrace of an error, we'll limit the backtrace
      // and limit the total length of each argument.
      $data['backtrace'] = array_slice($data['backtrace'], 0, 10);
      foreach ($data['backtrace'] as &$frame) {
        if (!isset($frame['args'])) {
          continue;
        }

        foreach ($frame['args'] as &$arg) {
          $arg = substr($this->convertToString($arg), 0, 500);
        }
      }
    }

    return parent::convertToString($data);
  }

}
