<?php

namespace Drupal\notifications\Encoder;

use Drupal\serialization\Encoder\JsonEncoder as SerializationJsonEncoder;

/**
 * Decodes form data and returns JSON.
 *
 * Simply respond to form format requests using the JSON encoder.
 */
class FormJsonEncoder extends SerializationJsonEncoder {

  /**
   * The formats that this Encoder supports.
   *
   * @var string
   */
  protected static $format = ['form'];

  /**
   * {@inheritdoc}
   */
  public function decode($data, $format, array $context = []) {
    parse_str($data, $result);
    return $result;
  }

}
