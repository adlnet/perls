<?php

namespace Drupal\content_moderation_additions\Plugin\RulesAction;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rules\Core\RulesActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Fetch field value by id' action.
 *
 * @RulesAction(
 *   id = "rules_entity_field_fetch_by_id",
 *   label = @Translation("Fetch field value by id(string)"),
 *   category = @Translation("Entity"),
 *   context_definitions = {
 *     "type" = @ContextDefinition("string",
 *       label = @Translation("Entity type"),
 *       description = @Translation("Specify the type of the entity that should be fetched."),
 *       options_provider = "\Drupal\rules\Plugin\OptionsProvider\EntityTypeOptions",
 *       assignment_restriction = "input"
 *     ),
 *     "field_name" = @ContextDefinition("string",
 *       label = @Translation("Field name"),
 *       description = @Translation("Name of the field."),
 *       options_provider = "\Drupal\rules\Plugin\OptionsProvider\FieldListOptions",
 *     ),
 *     "entity_id" = @ContextDefinition("integer",
 *       label = @Translation("Identifier"),
 *       description = @Translation("The id of the entity.")
 *     ),
 *   },
 *   provides = {
 *     "field_value_fetched" = @ContextDefinition("string",
 *       label = @Translation("Fetched field value(string)")
 *     ),
 *   }
 * )
 */
class EntityFieldFetchById extends RulesActionBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a EntityFetchById object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Executes the action with the given context.
   *
   * @param string $type
   *   The entity type id.
   * @param string $field_name
   *   The field name.
   * @param int $entity_id
   *   The entity id.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function doExecute(string $type, string $field_name, int $entity_id) {
    $storage = $this->entityTypeManager->getStorage($type);
    $entity = $storage->load($entity_id);
    if (!empty($entity) && $entity instanceof ContentEntityInterface) {
      $field_value = $entity->get($field_name)->getString();
      $this->setProvidedValue('field_value_fetched', $field_value);
    }
  }

}
