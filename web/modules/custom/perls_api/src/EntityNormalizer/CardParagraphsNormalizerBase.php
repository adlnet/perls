<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\Core\TypedData\Plugin\DataType\Map;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Base class which support normalization of specific paragraph fields.
 */
class CardParagraphsNormalizerBase implements NormalizerInterface {

  /**
   * Name of a drupal field which is a paragraph field.
   */
  const PARAGRAPH_FIELD_NAME = '';

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * CardFrontParagraphNormalizer constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager) {
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    $output = [];
    if ($data->hasField(static::PARAGRAPH_FIELD_NAME) && $data->get(static::PARAGRAPH_FIELD_NAME) instanceof EntityReferenceRevisionsFieldItemList) {
      $field_values = $data->get(static::PARAGRAPH_FIELD_NAME)->referencedEntities();
      foreach ($field_values as $field_value) {
        $output[] = $this->prepareParagraphOutput($field_value, $data);
      }
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof EntityInterface;
  }

  /**
   * Get a paragraph object and it convert to expected format.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   A paragraph object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity containing the paragraph output.
   *
   * @return array
   *   The generated output.
   */
  protected function prepareParagraphOutput(ParagraphInterface $paragraph, EntityInterface $entity) {
    $field_mapping = $this->fieldMapping();

    $output = [];
    $output['id'] = $paragraph->uuid();
    $output['type'] = $paragraph->getType();
    $output['fields'] = [];
    $field_values = $field_mapping[$paragraph->getType()];

    if ($paragraph->getType() === 'image') {
      /** @var \Drupal\field_layout\Entity\FieldLayoutEntityFormDisplay $entity_form_display */
      $entity_form_display = EntityFormDisplay::load($paragraph->getEntityTypeId() . '.' . $paragraph->bundle() . '.' . 'default');
      $field_form_list = $entity_form_display->getComponents();
      $field_values = $this->fieldListOrder($field_form_list, $field_values);
    }

    foreach ($field_values as $field_name => $field_data) {
      /** @var \Drupal\Core\Field\FieldItemInterface $field_value */
      $field_value = $paragraph->get($field_name)->first();
      if ($field_value) {
        $field_output = ['type' => $field_data['type']];
        $field_function = $this->getFieldFunction($field_name);
        // Call the specific function like $this->bodyAttributes(...).
        $field_output['attributes'] = call_user_func_array(
          [$this, $field_function],
          [$field_data, $field_value]
        );
        $output['fields'][] = $field_output;
      }
    }

    return $output;
  }

  /**
   * Contains some specific property of field, which should appear in output.
   *
   * @return array
   *   The field property array.
   */
  private function fieldMapping() {
    return [
      'text' => [
        'field_paragraph_body' => [
          'type' => 'Label',
          'TextType' => 'Html',
        ],
      ],
      'image' => [
        'field_caption' => [
          'type' => 'Label',
          'TextType' => 'Text',
          'Style' => 'Caption',
        ],
        'field_media_image' => [
          'type' => 'Image',
        ],
        'field_title' => [
          'type' => 'Label',
          'TextType' => 'Text',
          'Style' => 'Subtitle',
        ],
      ],
    ];
  }

  /**
   * Contains a mapping between field name and function.
   *
   * @param string $field_name
   *   The drupal field name.
   *
   * @return array
   *   The mapping array.
   */
  private function getFieldFunction($field_name) {
    $function_list = [
      'field_paragraph_body' => 'bodyAttributes',
      'field_title' => 'imageTextFieldAttributes',
      'field_caption' => 'imageTextFieldAttributes',
      'field_media_image' => 'imageSrcFieldAttributes',
    ];

    return $function_list[$field_name];
  }

  /**
   * Prepare the attributes for text field which part of text field.
   *
   * @param array $field_data
   *   The field mapping which contains some pre-configured output.
   * @param object $field_value
   *   The value of drupal field.
   *
   * @return array
   *   The expected output.
   */
  private function bodyAttributes(array $field_data, $field_value) {
    return [
      'Text' => trim($this->getText($field_value)),
      'TextType' => $field_data['TextType'],
    ];
  }

  /**
   * Retrieves the raw text value from a FieldItemInterface.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $field_value
   *   The field item.
   *
   * @return string
   *   The text.
   */
  private function getText(FieldItemInterface $field_value) {
    // Formatted text fields (e.g. TextLongItem) have their value set as
    // a Map, so we need to get the value from the Map.
    if ($field_value instanceof Map) {
      return $field_value->get('value')->getValue();
    }

    return $field_value->getValue();
  }

  /**
   * Prepare the attributes for text field which part of image field.
   *
   * @param array $field_data
   *   The field mapping which contains some pre-configured output.
   * @param object $field_value
   *   The value of drupal field.
   *
   * @return array
   *   The expected output.
   */
  private function imageTextFieldAttributes(array $field_data, $field_value) {
    return [
      'Text' => $field_value->getString(),
      'TextType' => $field_data['TextType'],
      'Style' => $field_data['Style'],
    ];
  }

  /**
   * Generate proper output for media field.
   *
   * @param array $field_data
   *   The field mapping which contains some pre-configured output.
   * @param object $field_value
   *   The value of drupal field.
   *
   * @return array
   *   The expected output.
   */
  private function imageSrcFieldAttributes(array $field_data, $field_value) {
    $output = [];
    // Load large image style.
    /** @var \Drupal\image\ImageStyleInterface $large_image */
    $large_image = ImageStyle::load('large');
    if (isset($field_value->entity) && $field_value->entity instanceof File) {
      $output['Source'] = $large_image->buildUrl($field_value->entity->getFileUri());
    }
    elseif (isset($field_value->entity) && $field_value->entity instanceof Media) {
      $image_url = NULL;
      /** @var \Drupal\media\Entity\Media $entity */
      $media = $field_value->entity;
      if ($media->bundle() === 'image' && $media->hasField('field_media_image')) {
        $image_list = $media->get('field_media_image')->referencedEntities();
        if (!empty($image_list)) {
          $image_url = $large_image->buildUrl($image_list[0]->getFileUri());
        }
      }
      $output['Source'] = $image_url;
    }

    $output['Aspect'] = 'AspectFit';
    return $output;
  }

  /**
   * Reorder the original field list to expected order.
   *
   * @param array $form_field_list
   *   The field form config.
   * @param array $original_order
   *   This the original field list with all values.
   *
   * @return array
   *   The re-ordered field list.
   */
  private function fieldListOrder(array $form_field_list, array $original_order) {
    $in_order = [];
    uasort($form_field_list, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    foreach ($form_field_list as $field_name => $field_data) {
      $in_order[$field_name] = $original_order[$field_name];
    }

    return $in_order;
  }

}
