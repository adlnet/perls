<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Specific field normalizer for field_card_front field.
 */
class CardFrontParagraphNormalizer extends CardParagraphsNormalizerBase {

  /**
   * {@inheritdoc}
   */
  const PARAGRAPH_FIELD_NAME = 'field_card_front';

  /**
   * {@inheritdoc}
   */
  protected function prepareParagraphOutput(ParagraphInterface $paragraph, EntityInterface $entity) {
    $output = parent::prepareParagraphOutput($paragraph, $entity);

    // The front of flashcards should be treated as a heading.
    if ($entity->getType() === 'flash_card' && $paragraph->getType() === 'text') {
      $output['fields'][0]['attributes']['Style'] = 'Heading';
    }

    return $output;
  }

}
