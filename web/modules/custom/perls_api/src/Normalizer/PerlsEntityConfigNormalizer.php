<?php

namespace Drupal\perls_api\Normalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_normalization\EntityNormalizationManagerInterface;
use Drupal\entity_normalization\Normalizer\EntityConfigNormalizer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extended EntityConfigNormalizer normalizer.
 */
class PerlsEntityConfigNormalizer extends EntityConfigNormalizer {

  /**
   * Node storage manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * PerlsEntityConfigNormalizer constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The current service container.
   * @param \Drupal\entity_normalization\EntityNormalizationManagerInterface $normalizationManager
   *   The plugin manager for entity normalization definitions.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Drupal entity type manager.
   */
  public function __construct(
    ContainerInterface $container,
    EntityNormalizationManagerInterface $normalizationManager,
    EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($container, $normalizationManager);
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $object */
    $config = $this->normalizationManager->getEntityConfig($object, $format);

    $result = [];

    $fields = $config->getFields();
    foreach ($fields as $field) {
      if (!$field->isRequired() && !$object->hasField($field->getId())) {
        // The field isn't required and we don't have the field, skip it.
        continue;
      }
      $context['field_config'] = $field;

      $normalized = NULL;
      /** @var \Drupal\entity_normalization\FieldConfig $field */
      switch ($field->getType()) {
        case 'pseudo':
          $nName = $field->getNormalizerName();
          if ($nName !== NULL && $this->container->has($nName)) {
            $normalizer = $this->container->get($nName);
            if ($normalizer->supportsNormalization($object, $format, $context)) {
              $normalized = $normalizer->normalize($object, $format, $context);
            }
          }
          break;

        case 'inherited':
          /** @var \Drupal\perls_api\PerlsFieldConfig $field */
          $parent = $this->getParentEntity([
            'parent' => $field->getParent(),
            'field' => $field->getReferenceField(),
          ], $object);
          if ($parent) {
            $def = $parent->get($field->getInheritedFieldName());
            $normalized = $this->normalizer->normalize($def, $format, $context);
          }
          break;

        default:
          $def = $object->get($field->getId());
          $normalized = $this->normalizer->normalize($def, $format, $context);
          break;
      }
      if (!empty($group = $field->getGroup())) {
        $result[$group][$field->getName()] = $normalized;
      }
      else {
        $result[$field->getName()] = $normalized;
      }
    }

    foreach ($config->getNormalizers() as $normalizer) {
      /** @var \Symfony\Component\Serializer\Normalizer\NormalizerInterface $n */
      $n = $this->container->get($normalizer);
      if ($n->supportsNormalization($object, $format)) {
        $result = array_merge($result, $n->normalize($object, $format, $context));
      }
    }

    return $result;
  }

  /**
   * Load the parent entity.
   *
   * @param array $conditions
   *   An array which contains values from normalizer yml.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The child entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The parent entity.
   */
  protected function getParentEntity(array $conditions, EntityInterface $entity) {
    $parent = NULL;
    $query = $this->nodeStorage->getQuery();
    $parent_ids = $query
      ->condition('type', $conditions['parent'])
      ->condition('status', 1)
      ->condition($conditions['field'], $entity->id())
      ->execute();

    if (!empty($parent_ids)) {
      $parent = $this->nodeStorage->load(reset($parent_ids));
    }

    return $parent;
  }

}
