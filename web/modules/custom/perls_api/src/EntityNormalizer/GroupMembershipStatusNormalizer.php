<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\group\Entity\GroupContentInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer to check whether user can leave group.
 */
class GroupMembershipStatusNormalizer implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    if (!$data->getGroup()->access('leave group')) {
      return 'locked';
    }

    return 'open';
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof GroupContentInterface;
  }

}
