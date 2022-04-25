<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\node\Entity\Node;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * WebLinkUrlNormalizer for URL in WebLink CT.
 */
class WebLinkUrlNormalizer implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $linkType = $object->get('field_link_type')->getString();
    $customUri = $object->get('field_custom_uri')->getString();
    // Set Output as nodeURL by default.
    $output = $object->toUrl()->toString(TRUE)->getGeneratedUrl();

    if (!empty($linkType)) {
      if ($linkType == 'custom' && !empty($customUri)) {
        $output = $customUri;
      }
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL): bool {
    return $data instanceof Node;
  }

}
