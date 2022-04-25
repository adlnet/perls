<?php

namespace Drupal\xapi;

use Drupal\Core\Url;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Plugin\Exception\ContextException;

/**
 * XAPI activities and entities.
 */
class XapiActivityProvider implements XapiActivityProviderInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ActivityDefinitionProvider object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getActivity(EntityInterface $entity): array {
    return [
      'objectType' => 'Activity',
      'id' => $this->getActivityId($entity),
      'definition' => $this->getActivityDefinition($entity),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(array $activity): ?EntityInterface {
    if (!isset($activity['id'])) {
      return NULL;
    }

    if (isset($activity['objectType']) && $activity['objectType'] !== 'Activity') {
      return NULL;
    }

    return $this->getEntityForActivityId($activity['id']);
  }

  /**
   * Retrieves an activity ID from a Drupal entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Drupal entity.
   *
   * @return string
   *   The activity ID for the entity.
   */
  protected function getActivityId(EntityInterface $entity): string {
    // @todo May be impacted by alias?
    return $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
  }

  /**
   * Generates the activity definition for a Drupal entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Drupal entity.
   *
   * @return array
   *   The activity definition for the entity.
   */
  protected function getActivityDefinition(EntityInterface $entity): array {
    $definition = [];
    $name = $this->getActivityName($entity);
    if (!empty($name)) {
      $definition['name'] = $name;
    }

    $description = $this->getActivityDescription($entity);
    if (!empty($description)) {
      $definition['description'] = $description;
    }

    $type = $this->getActivityType($entity);
    if (!empty($type)) {
      $definition['type'] = $type;
    }

    $extensions = $this->getActivityExtensions($entity);
    if (!empty($extensions)) {
      $definition['extensions'] = $extensions;
    }

    return $definition;
  }

  /**
   * Determines the activity name for a Drupal entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Drupal entity.
   *
   * @return array
   *   A language map for the activity name of the entity.
   */
  protected function getActivityName(EntityInterface $entity): array {
    $name = [];

    if ($entity instanceof TranslatableInterface) {
      foreach ($entity->getTranslationLanguages() as $lang_code => $language) {
        $translated_entity = $entity->getTranslation($lang_code);
        $name[$lang_code] = $translated_entity->label();
      }
    }
    else {
      // @todo Don't assume English.
      $name['en'] = $entity->label();
    }

    return $name;
  }

  /**
   * Determines the activity description for a Drupal entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Drupal entity.
   *
   * @return array
   *   A language map for the activity definition of the entity.
   */
  protected function getActivityDescription(EntityInterface $entity): array {
    return [];
  }

  /**
   * Determines the activity type for a Drupal entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Drupal entity.
   *
   * @return string|null
   *   The activity type.
   */
  protected function getActivityType(EntityInterface $entity): ?string {
    switch ($entity->getEntityTypeId()) {
      case 'comment':
        return 'http://activitystrea.ms/schema/1.0/comment';

      case 'file':
        return 'http://id.tincanapi.com/activitytype/document';

      case 'node':
        return 'http://activitystrea.ms/schema/1.0/article';

      case 'taxonomy_term':
        return 'http://id.tincanapi.com/activitytype/category';

      case 'user':
        return 'http://id.tincanapi.com/activitytype/user-profile';

      default:
        return NULL;
    }
  }

  /**
   * Gets extensions to append to the activity definition.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Drupal entity.
   *
   * @return array
   *   Extensions to append to the activity definition.
   */
  protected function getActivityExtensions(EntityInterface $entity): array {
    return [];
  }

  /**
   * Loads a Drupal entity ID based on activity ID (URL).
   *
   * @param string $activityId
   *   The activity ID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The cooresponding Drupal entity, or null if no entity was found.
   */
  protected function getEntityForActivityId(string $activityId): ?EntityInterface {
    $path = parse_url($activityId, PHP_URL_PATH);
    if (!$path) {
      return NULL;
    }

    try {
      $url = Url::fromUserInput($path);
    }
    catch (ContextException $e) {
      // It's possible that if the entity is in the process of being removed
      // that we cannot resolve the current context, so we'll return no entity.
      return NULL;
    }

    if (!$url->isRouted()) {
      return NULL;
    }

    $params = $url->getRouteParameters();
    $entity_type = key($params);

    if (!$entity_type) {
      return NULL;
    }

    if (!$this->entityTypeManager->hasDefinition($entity_type)) {
      return NULL;
    }

    return $this->entityTypeManager->getStorage($entity_type)->load($params[$entity_type]);
  }

}
