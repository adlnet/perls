<?php

namespace Drupal\perls_api\Normalizer;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\serialization\Normalizer\EntityNormalizer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes custom Web link content array into node objects.
 */
class LearnLinkEntityNormalizer extends EntityNormalizer implements DenormalizerInterface {

  const LEARN_LINK_FIELD_MAPPING = [
    'name' => 'title',
    'topic' => 'field_topic',
    'tags' => 'field_tags',
    'difficulty' => 'field_difficulty',
    'description' => 'field_description',
    'is_promoted' => 'promote',
    'is_sticky' => 'sticky',
    'published' => 'status',
    'url' => 'field_content_link',
    'link_type' => 'field_link_type',
    'custom_uri' => 'field_custom_uri',
    'groups' => 'entitygroupfield',
    'feature_image' => 'field_media_image',
  ];

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Group normalizer service.
   *
   * @var \Drupal\perls_api\Normalizer\GroupNormalizer
   */
  protected $groupNormalizer;

  /**
   * Constructs an EntityNormalizer object for learn_link bundle.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface $entity_type_repository
   *   The entity type repository.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\perls_api\Normalizer\GroupNormalizer $group_normalizer
   *   Group normalizer service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              EntityTypeRepositoryInterface $entity_type_repository,
                              EntityFieldManagerInterface $entity_field_manager,
                              RequestStack $request_stack,
                              GroupNormalizer $group_normalizer) {
    parent::__construct($entity_type_manager, $entity_type_repository, $entity_field_manager);
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->groupNormalizer = $group_normalizer;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (is_array($data)) {
      static::preProcessData($data);
    }
    if (isset($data['entitygroupfield'])) {
      // Set the group field values on $data for PATCH method.
      list($data, $entity) = $this->setGroupField($data, $class, $format, $context);
      if ($context['request_method'] === 'post') {
        // New entity is denormalized and saved when group field is being set.
        // We can directly return this pre-saved entity.
        return $entity;
      }
    }

    return parent::denormalize($data, $class, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    if (parent::supportsDenormalization($data, $type, $format) && $type === Node::class) {
      if (is_array($data) && $data['type'] == 'learn_link') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Replace the aliases in the input data with actual field names and formats.
   *
   * @param array $data
   *   Input data.
   */
  protected static function preProcessData(array &$data = []) {
    // Keep the content unpublished unless specified explicitly.
    if (!isset($data['published'])) {
      $data['published'] = FALSE;
    }

    // The URL field should be required.
    if (empty($data['url'])) {
      throw new UnprocessableEntityHttpException("Unprocessable Entity: validation failed. `url` should not be null.");
    }

    foreach ($data as $key => $value) {
      switch ($key) {
        case 'name':
        case 'description':
        case 'is_promoted':
        case 'is_sticky':
        case 'published':
        case 'link_type':
          $data[static::LEARN_LINK_FIELD_MAPPING[$key]] = ['value' => $value];
          unset($data[$key]);
          break;

        case 'groups':
        case 'tags':
          if (!is_array($value)) {
            throw new BadRequestHttpException("Field {$key} accepts multiple values, please format the parameter as an array.");
          }
        case 'topic':
        case 'feature_image':
        case 'difficulty':
          if (is_string($value)) {
            $data[static::LEARN_LINK_FIELD_MAPPING[$key]] = ['target_id' => $value];
          }
          if (is_array($value)) {
            foreach ($value as $term) {
              $data[static::LEARN_LINK_FIELD_MAPPING[$key]][] = ['target_id' => $term];
            }
          }
          unset($data[$key]);
          break;

        case 'url':
          unset($data[$key]);
          if (isset($data['link_type'])) {
            $link_type = $data['link_type'];
            // By default, URL value will be assigned to Content link field.
            if (!empty($link_type) && $link_type === 'custom') {
              // If link type is custom, URL will be saved as field_custom_uri.
              $key = 'custom_uri';
            }
          }
          else {
            // Set default value for link type.
            $data[static::LEARN_LINK_FIELD_MAPPING['link_type']] = ['value' => 'web'];
          }
          $data[static::LEARN_LINK_FIELD_MAPPING[$key]] = ['href' => $value];
          break;

        default:
          break;
      }
    }

  }

  /**
   * Sets value of group content fields.
   *
   * @param array $data
   *   Data received.
   * @param string $class
   *   Class name.
   * @param string $format
   *   Format.
   * @param array $context
   *   Context.
   *
   * @return array
   *   Array of Data and original entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
   */
  public function setGroupField(array $data, string $class, string $format, array $context) {

    // We have to save the group field value programmatically, unset the
    // element from $data to avoid redundancy in the field's post_save hook.
    $group_name = $data['entitygroupfield'];
    unset($data['entitygroupfield']);

    // Denormalize entity for POST requests so that we can save it and use
    // its entity ID for fetching the group content.
    $entity = parent::denormalize($data, $class, $format, $context);

    if ($context['request_method'] === 'post') {
      $this->checkAccess($entity);
      // Save the entity to generate entity id to fetch its group content.
      $entity->save();
    }
    if ($context['request_method'] === 'patch') {
      // Fetch the original entity for its entity ID.
      $this->checkAccess($entity, 'update');
      $entity = $this->currentRequest->get('node');
    }

    if (!empty($entity) && $entity instanceof NodeInterface) {
      $groups = $this->getGroups($entity, $context, $group_name, $format);

      // Save the entity group field.
      $entity->set('entitygroupfield', $groups);
      $entity->save();
    }
    return [$data, $entity];

  }

  /**
   * Get group content IDs for current node.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Current node entity.
   * @param array $context
   *   Context.
   * @param string $group_name
   *   Group name.
   * @param string $format
   *   Request format.
   *
   * @return array
   *   Returns array of group content IDs.
   *
   * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
   */
  protected function getGroups(EntityInterface $entity, array $context, $group_name, string $format): array {
    $context['entity'] = $entity;
    $field_item_list = $entity->get('entitygroupfield');
    $groupItemListClass = get_class($field_item_list);
    $context['target_instance'] = $field_item_list;

    return $this->groupNormalizer->denormalize(
      $group_name,
      $groupItemListClass,
      $format,
      $context
    );
  }

  /**
   * Checks create/edit access for current node.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Current entity.
   * @param string $op
   *   Node Operation.
   */
  protected function checkAccess(EntityInterface $entity, string $op = 'create') {
    // Check access before saving the entity.
    $entity_access = $entity->access($op, NULL, TRUE);
    if (!$entity_access->isAllowed()) {
      $message = "You are not authorized to {$op} this {$entity->getEntityTypeId()} content of {$entity->bundle()} bundle";
      throw new AccessDeniedHttpException($entity_access->getReason() ?: $message);
    }
  }

  /**
   * This class will be used only for denormalization, disable normalization.
   *
   * @inheritDoc
   */
  public function supportsNormalization($data, $format = NULL) {
    return FALSE;
  }

}
