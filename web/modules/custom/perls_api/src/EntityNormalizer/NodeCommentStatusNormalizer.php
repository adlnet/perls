<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer to check comments status on the given node.
 */
class NodeCommentStatusNormalizer implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    $field = $context['field_config']->getId();
    $status = $data->get($field)->status;
    if (NULL === $status) {
      return 'hidden';
    }
    $statuses = [
      CommentItemInterface::HIDDEN => 'hidden',
      CommentItemInterface::CLOSED => 'closed',
      CommentItemInterface::OPEN => 'open',
    ];

    return $statuses[$status];
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof NodeInterface;
  }

}
