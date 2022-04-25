<?php

namespace Drupal\rules_date_recur\Plugin\RulesEvent;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives entity occurrence rule events for each content entity type.
 */
class EntityOccurrenceDeriver extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->stringTranslation = $container->get('string_translation');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      // Only allow content entities and ignore configuration entities.
      if (!$entity_type instanceof ContentEntityTypeInterface) {
        continue;
      }

      $this->derivatives[$entity_type_id] = [
        'label' => $this->t('When a @entity_type is @event', [
          '@entity_type' => $entity_type->getSingularLabel(),
          '@event' => $base_plugin_definition['event'],
        ]),
        'category' => $entity_type->getLabel(),
        'entity_type_id' => $entity_type_id,
        'context_definitions' => [
          $entity_type_id => [
            'type' => "entity:$entity_type_id",
            'label' => $entity_type->getLabel(),
          ],
          'field' => [
            'type' => 'string',
            'label' => $this->t('Field name'),
          ],
          'start_date' => [
            'type' => 'datetime_iso8601',
            'label' => $this->t('Starting date'),
          ],
          'end_date' => [
            'type' => 'datetime_iso8601',
            'label' => $this->t('Ending date'),
          ],
        ],
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
