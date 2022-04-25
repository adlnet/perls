<?php

namespace Drupal\perls_api\Normalizer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\field\Entity\FieldConfig;
use Drupal\serialization\Normalizer\FieldItemNormalizer;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes field item object structure by updating the entity field values.
 */
class TermsNormalizer extends FieldItemNormalizer implements DenormalizerInterface {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = EntityReferenceItem::class;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an EntityReferenceFieldItemNormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    $definition = $context['target_instance']->getFieldDefinition();
    $settings = $definition->getSettings();
    // Denormalize only if the reference entity target type is taxonomy term.
    if ($definition instanceof FieldConfig && isset($settings['target_type']) && $settings['target_type'] === 'taxonomy_term') {
      $id = $this->getTargetId($definition, $data);
      return parent::denormalize($id, $class, $format, $context);
    }
    return parent::denormalize($data, $class, $format, $context);
  }

  /**
   * Check if the de-normalization is only applied to entity reference fields.
   *
   * @inheritDoc
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return $type === EntityReferenceItem::class;
  }

  /**
   * Find target IDs for the terms.
   *
   * @param \Drupal\field\Entity\FieldConfig $config
   *   Field configurations.
   * @param string|array $data
   *   Data received.
   *
   * @return int|string|null
   *   Target id(s).
   */
  protected function getTargetId(FieldConfig $config, $data) {
    $tid = NULL;
    // If just one term is provided.
    $cardinality = $config->getFieldStorageDefinition()->getCardinality();
    // The data format will be different based on field cardinality.
    if ($cardinality == 1) {
      $tid = $this->getTermId($config, $data);
    }
    elseif (is_array($data)) {
      // When the cardinality is not 1, the data format should be an array.
      foreach ($data as $key => $termName) {
        if ($tid = $this->getTermId($config, $termName)) {
          $tid[$key] = $tid;
        }
      }
    }
    return $tid;
  }

  /**
   * Find term ids for the taxonomy term entities.
   *
   * @param \Drupal\field\Entity\FieldConfig $config
   *   Field configurations.
   * @param string $termName
   *   Term name.
   *
   * @return int|null
   *   Term id, null if no term ids are found.
   */
  protected function getTermId(FieldConfig $config, string $termName) {
    $settings = $config->getSettings();

    if (isset($settings['handler_settings']['target_bundles']) &&
      !empty($vocab_ids = $settings['handler_settings']['target_bundles'])) {
      foreach ($vocab_ids as $vid) {
        $properties = [
          'vid' => $vid,
          'name' => $termName,
        ];
        // Attempt to fetch the term entity id.
        $terms = $this->entityTypeManager
          ->getStorage($settings['target_type'])
          ->loadByProperties($properties);
        if (empty($terms) && isset($settings['handler_settings']['auto_create']) && $settings['handler_settings']['auto_create']) {
          // Create a term if it does not exist.
          try {
            $term = $this->createTag($termName, $vid);
          }
          catch (\Exception $e) {
            throw new \Exception($e->getMessage());
          }
        }
        elseif (empty($terms)) {
          return NULL;
        }
        else {
          $term = reset($terms);
        }
        if ($term instanceof TermInterface) {
          return $term->id();
        }
      }

    }
  }

  /**
   * Creates new term.
   *
   * @param string $name
   *   Term name.
   * @param string $vid
   *   Term vocabulary id.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Returns the created entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createTag(string $name, string $vid) {
    $term = $this->entityTypeManager->getStorage('taxonomy_term')
      ->create([
        'name' => $name,
        'vid' => $vid,
      ]);
    $term->save();

    return $term;
  }

}
