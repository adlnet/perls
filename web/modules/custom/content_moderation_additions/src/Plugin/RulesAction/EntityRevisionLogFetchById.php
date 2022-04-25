<?php

namespace Drupal\content_moderation_additions\Plugin\RulesAction;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\rules\Core\RulesActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Fetch entity revision log message by id' action.
 *
 * @RulesAction(
 *   id = "rules_entity_revision_fetch_by_id",
 *   label = @Translation("Fetch entity revision log message by id"),
 *   category = @Translation("Entity"),
 *   context_definitions = {
 *     "type" = @ContextDefinition("string",
 *       label = @Translation("Entity type"),
 *       description = @Translation("Specify the type of the entity that should be fetched."),
 *       options_provider = "\Drupal\rules\Plugin\OptionsProvider\EntityTypeOptions",
 *       assignment_restriction = "input"
 *     ),
 *     "entity_id" = @ContextDefinition("integer",
 *       label = @Translation("Identifier"),
 *       description = @Translation("The id of the entity.")
 *     ),
 *   },
 *   provides = {
 *     "log_fetched" = @ContextDefinition("string",
 *       label = @Translation("Fetched entity log")
 *     ),
 *   }
 * )
 *
 * @todo Add access callback information from Drupal 7.
 * @todo Port for rules_entity_action_type_options.
 */
class EntityRevisionLogFetchById extends RulesActionBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

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
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation information service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModerationInformationInterface $moderation_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationInfo = $moderation_info;
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
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * Executes the action with the given context.
   *
   * @param string $type
   *   The entity type id.
   * @param int $entity_id
   *   The entity id.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function doExecute(string $type, int $entity_id) {
    $storage = $this->entityTypeManager->getStorage($type);
    $vid = $storage->getLatestRevisionId($entity_id);
    $entity = $storage->loadRevision($vid);
    $log = $entity->getRevisionLogMessage();
    $this->setProvidedValue('log_fetched', $log);
  }

}
