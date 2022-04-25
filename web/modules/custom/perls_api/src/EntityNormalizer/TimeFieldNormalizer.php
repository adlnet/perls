<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\notifications_goals\UserNotificationTimeConverter;
use Drupal\time_field\Plugin\Field\FieldType\TimeType;
use Drupal\time_field\Time;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalize value of a time field.
 */
class TimeFieldNormalizer implements NormalizerInterface, DenormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    if (!empty($object->getValue()['value'])) {
      $entity = $object->getParent()->getEntity();
      if ($entity) {
        return Time::createFromTimestamp(UserNotificationTimeConverter::convertTime($object->getValue()['value'], $entity, TRUE))->format();
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $type, $format = NULL, array $context = []) {
    // The default timezone will be the configured drupal timezone.
    $time = DateTimePlus::createFromFormat('h:i a', mb_strtolower($data));
    // The time zone set isn't solved this problem because that doesn't change
    // with time we need to use the offset which is the diff between UTC and
    // currently used timezone on the site.
    $timestamp = $time->getTimestamp() + $time->getOffset();
    return ($timestamp % 86400);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof TimeType;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return (bool) preg_match('#^(?:1[012]|0[0-9]):[0-5][0-9].?([AaPp][Mm])$#', $data);
  }

}
