<?php

namespace Drupal\xapi;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\Markup;
use Drupal\user\UserInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Convenience methods for loading Drupal entities based on xAPI statements.
 *
 * Supports both agents and activities.
 */
class XapiStatementHelper {

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannel|\Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Actor IFI type manager.
   *
   * @var \Drupal\xapi\XapiActorIFIManager
   */
  protected $ifiTypeManager;

  /**
   * The activity provider.
   *
   * @var \Drupal\xapi\XapiActivityProviderInterface
   */
  protected $activityProvider;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * XapiStatementHelper constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Drupal logger service.
   * @param \Drupal\xapi\XapiActorIFIManager $ifi_manager
   *   Manage the IFI type of an actor.
   * @param \Drupal\xapi\XapiActivityProviderInterface $activity_provider
   *   The activity provider.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger,
    XapiActorIFIManager $ifi_manager,
    XapiActivityProviderInterface $activity_provider,
    EntityTypeManagerInterface $entity_manager,
    ModuleHandlerInterface $module_handler) {
    $this->logger = $logger->get('xapi');
    $this->ifiTypeManager = $ifi_manager;
    $this->activityProvider = $activity_provider;
    $this->entityManager = $entity_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Retrieves the user from statement.
   *
   * @param object $statement
   *   A statement.
   *
   * @return bool|\Drupal\user\UserInterface
   *   A drupal user otherwise NULL.
   */
  public function getUserFromStatement(object $statement): ?UserInterface {
    if (!isset($statement->actor)) {
      return NULL;
    }

    return $this->getUserFromActor($statement->actor);
  }

  /**
   * Retrieves a user from an actor.
   *
   * @param object $actor
   *   The actor object.
   *
   * @return \Drupal\user\UserInterface|null
   *   The cooresponding Drupal user, or null.
   */
  public function getUserFromActor(object $actor): ?UserInterface {
    foreach ($this->ifiTypeManager->getDefinitions() as $plugin_id => $ifi_plugin) {
      /** @var \Drupal\xapi\XapiActorIFIInterface $instance */
      $instance = $this->ifiTypeManager->createInstance($plugin_id);
      try {
        if ($instance->isMyIfi($actor)) {
          return $instance->getUserFromActor($actor);
        }
      }
      catch (EntityStorageException $exception) {
        $this->logger->error($exception->getMessage());
      }
    }

    return NULL;
  }

  /**
   * Retrieves the Drupal entity from the object of the statement.
   *
   * @param object $statement
   *   The statement.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The associated Drupal entity, or null.
   */
  public function getEntityFromStatement(object $statement): ?EntityInterface {
    return $this->getEntityFromActivity($statement->object);
  }

  /**
   * Retrieves the node from statement object.
   *
   * @param object $statement
   *   The statement object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The load node otherwise NULL.
   */
  public function getContentFromState($statement) {
    $activity = $statement->object;
    return $this->getEntityFromActivity($activity);
  }

  /**
   * Retrieves the related Drupal entity from an activity.
   *
   * This will first look for the Drupal entity based on the canonical URI.
   * But, if no Drupal entity is found, it will look for an uploaded package
   * with a matching activity ID and return the associated entity.
   *
   * This differs from `xapi.activity_provider` which only
   * searches for a Drupal entity based on a matching canonical URI.
   *
   * @param object $activity
   *   The activity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The associated Drupal entity, or null.
   *
   * @see XapiActivityProviderInterface::getEntity()
   */
  public function getEntityFromActivity(object $activity): ?EntityInterface {
    if (!isset($activity->id)) {
      return NULL;
    }

    if (isset($activity->objectType) && $activity->objectType !== 'Activity') {
      return NULL;
    }

    // Ensure the activity is formatted as an array instead of an object.
    $activity = json_decode(json_encode($activity), TRUE);
    $result = $this->activityProvider->getEntity($activity);

    // If no Drupal entity was found based on the activity ID,
    // search the uploaded packages for a matching activity ID.
    if (!$result) {
      $entities = $this->getEntitiesWithPackageActivityId($activity['id']);
      $result = reset($entities) ?: NULL;

      // The caller is only expecting a single entity, but it's possible for
      // multiple packages to have been uploaded with the same activity ID.
      // In that case, we can't realistically disambiguate which entity the
      // caller is looking for, so we pick the first one.
      // We log a notice about this scenario to help for debugging purposes.
      if (count($entities) > 1) {
        $links = array_map(function ($entity) {
          try {
            return $entity->toLink()->toString();
          }
          catch (\Exception $e) {
            return $entity->label();
          }
        }, $entities);
        $this->logger->notice('Multiple packages have the activity ID: %activity_id<br>Each activity should have a unique ID.<br>@links', [
          '%activity_id' => $activity['id'],
          '@links' => Markup::create(implode('<br>', $links)),
        ]);
      }
    }

    return $result;
  }

  /**
   * Get the parent activity from a statement if it exists.
   */
  public function getParentFromStatement($statement) {
    $activity = $statement->context->contextActivities->parent[0];
    return $this->getEntityFromActivity($activity);
  }

  /**
   * Checks that the content of a statement object is valid.
   *
   * @param object $statement
   *   The statement object.
   *
   * @return bool
   *   TRUE if it's valid otherwise FALSE.
   */
  public function validate($statement) {
    $return = TRUE;
    // Checks that statement contains a valid actor.
    if (!$this->getUserFromStatement($statement)) {
      $return = FALSE;
    }

    // Checks that statement contains a valid activity.
    if (!$this->getContentFromState($statement)) {
      $return = FALSE;
    }

    // Implements a hook_xapi_statement_validator to allow to other modules to
    // validate the statement.
    $this->moduleHandler->invokeAll('xapi_statement_validator', [$statement]);

    return $return;
  }

  /**
   * Loads entities that are associated with an activity ID.
   *
   * Each uploaded xAPI package likely has it's own activity ID.
   * This finds every field that accepts xAPI packages and any entity
   * with an uploaded package with a matching activity ID.
   *
   * This can return multiple entities if more than one entity has
   * the same activity ID.
   *
   * @param string $activityId
   *   The activity ID to search for.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entities containing an uploaded eLearning package
   *   with a matching activity ID.
   */
  protected function getEntitiesWithPackageActivityId(string $activityId): array {
    if (empty($activityId)) {
      return [];
    }

    // Find all xAPI package fields and find the entity types
    // associated with those fields.
    $fields = $this->entityManager->getStorage('field_storage_config')
      ->loadByProperties([
        'type' => 'field_xapi_content_file_item',
      ]);

    $fields_by_entity = array_reduce($fields, function ($result, $field) {
      $result[$field->getTargetEntityTypeId()][] = $field->getName();
      return $result;
    });

    // For each entity type and their xAPI package fields,
    // load the associated entities with a matching activity ID.
    $entities = [];
    foreach ($fields_by_entity as $entity => $fields) {
      $storage = $this->entityManager->getStorage($entity);
      $query = $storage->getQuery();
      $field_conditions = $query->orConditionGroup();
      foreach ($fields as $field) {
        $field_conditions->condition("$field.activity_id", $activityId);
      }
      $query->condition($field_conditions);
      $result = $query->execute();
      $entities[$entity] = $storage->loadMultiple($result);
    }

    // Flattens the result to a single array of entities.
    // Keep in mind this could be entities of various types.
    $result = array_merge(...array_values($entities));

    return $result;
  }

}
