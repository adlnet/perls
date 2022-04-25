<?php

namespace Drupal\vidyo_platform\Api;

use Drupal\vidyo_platform\VidyoPlatformException;

/**
 * Represents an exception communicating with the Vidyo API.
 */
class VidyoApiException extends VidyoPlatformException {

  /**
   * Constructs a VidyoApiException.
   */
  public function __construct(string $message, ?\Throwable $previous = NULL, ?int $code = 0) {
    // Remove "SOAP-ERROR" from error message.
    $message = str_replace('SOAP-ERROR: ', '', $message);

    parent::__construct($message, $code, $previous);
  }

}
