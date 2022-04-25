<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Normalizer to turn an document to include URL.
 */
class DocumentUrlNormalizer extends CardParagraphsNormalizerBase {

  /**
   * {@inheritdoc}
   */
  const PARAGRAPH_FIELD_NAME = 'field_file';

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    if ($data->hasField(static::PARAGRAPH_FIELD_NAME) && $data->get(static::PARAGRAPH_FIELD_NAME) instanceof EntityReferenceRevisionsFieldItemList) {
      $field_values = $data->get(static::PARAGRAPH_FIELD_NAME)->referencedEntities();
      return $this->prepareParagraphOutput($field_values[0], $data);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareParagraphOutput(ParagraphInterface $paragraph, EntityInterface $entity) {

    if ($paragraph->getType() === 'document') {
      /** @var \Drupal\file\FileInterface[] $files */
      $files = $paragraph->get('field_document')->referencedEntities();

      if (!isset($files[0])) {
        return NULL;
      }

      $file = $files[0];

      return [
        'id' => $file->uuid(),
        'name' => $file->getFilename(),
        'url' => $file->createFileUrl(FALSE),
        'mimetype' => $file->getMimeType(),
      ];

    }

    return NULL;
  }

}
