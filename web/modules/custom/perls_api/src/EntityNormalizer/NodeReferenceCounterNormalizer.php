<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\node\NodeInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer to count the number of node reference field.
 */
class NodeReferenceCounterNormalizer implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    $field = $context['field_config']->getId();
    $references = $data->get($field)->getValue();

    $relatedIDs = array_column($references, 'target_id');

    if (!empty($relatedIDs)) {
      $query = \Drupal::entityQuery('node');
      $query->condition('nid', $relatedIDs, 'IN');
      $query->condition('status', 1);
      return $query->count()->execute();
    }

    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof NodeInterface;
  }

}
