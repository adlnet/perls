<?php

namespace Drupal\perls_api\Normalizer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_normalization\Normalizer\FieldItemListNormalizer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes field item object structure by updating the entity field values.
 */
class GroupNormalizer extends FieldItemListNormalizer implements DenormalizerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an EntityReferenceFieldItemNormalizer object.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The current service container.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Drupal entity type manager.
   */
  public function __construct(ContainerInterface $container, EntityTypeManagerInterface $entity_type_manager) {
    $this->container = $container;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    $entity = $context['entity'] ?? NULL;
    $definition = $context['target_instance']->getFieldDefinition()
      ->getItemDefinition();
    $settings = $definition->getSettings();

    $this->getTargetId($data, $settings, $entity);
    return $data;
  }

  /**
   * Helper function to get group target id.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getTargetId(&$data, $settings, $entity = NULL) {
    if (is_array($data)) {
      foreach ($data as $key => $label) {
        // Load all the group content for this entity.
        $group = $this->entityTypeManager
          ->getStorage('group')
          ->loadByProperties([
            'label' => $label['target_id'],
          ]);

        if (empty($group)) {
          throw new BadRequestHttpException("Group with name {$label['target_id']} not found. Please use an existing group name.");
        }

        $group = reset($group);
        $entity_bundle = $entity->bundle();
        $contentPlugin = $group->getGroupType()->getContentPlugin('group_node:' . $entity_bundle);
        $group_base_id = $contentPlugin->getBaseId();
        $group_bundle = $group->get('type')->getString();

        $type = sprintf("%s-%s-%s", $group_bundle, $group_base_id, $entity_bundle);
        $storage = $this->entityTypeManager->getStorage($settings['target_type']);
        $values = [
          'gid' => $group->id(),
          'type' => $type,
          'entity_id' => $entity->id(),
        ];
        $group_content = $storage->loadByProperties($values);
        $group_content = !empty($group_content) ? reset($group_content) : NULL;
        if (empty($group_content)) {
          $values['title'] = $entity->label();
          $values['uid'] = \Drupal::currentUser()->id();
          $group_content = $storage->create($values);
          $group_content->save();
        }
        $data[$key] = $group_content->id();
      }
    }
  }

  /**
   * Makes sure this normalizer is not run automatically.
   *
   * @inheritDoc
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    // We do not want this normalizer to be called automatically.
    return FALSE;
  }

  /**
   * Disable normalisation through this class.
   *
   * @inheritDoc
   */
  public function supportsNormalization($data, $format = NULL, array $context = []) {
    return FALSE;
  }

}
