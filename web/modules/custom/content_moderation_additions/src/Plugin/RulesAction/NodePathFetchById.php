<?php

namespace Drupal\content_moderation_additions\Plugin\RulesAction;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rules\Core\RulesActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Fetch node path by id' action.
 *
 * @RulesAction(
 *   id = "rules_node_path_fetch_by_id",
 *   label = @Translation("Fetch node path by id"),
 *   category = @Translation("Node"),
 *   context_definitions = {
 *     "nid" = @ContextDefinition("integer",
 *       label = @Translation("Identifier"),
 *       description = @Translation("The nid of the entity.")
 *     ),
 *   },
 *   provides = {
 *     "path_fetched" = @ContextDefinition("string",
 *       label = @Translation("Fetched node path")
 *     ),
 *   }
 * )
 */
class NodePathFetchById extends RulesActionBase implements ContainerFactoryPluginInterface {

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
      $container->get('entity_type.manager')
    );
  }

  /**
   * Executes the action with the given context.
   *
   * @param int $entity_id
   *   The entity id.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function doExecute(int $entity_id) {
    $storage = $this->entityTypeManager->getStorage('node');
    $node = $storage->load($entity_id);
    $path = $node->toUrl()->setAbsolute()->toString();
    $this->setProvidedValue('path_fetched', $path);
  }

}
