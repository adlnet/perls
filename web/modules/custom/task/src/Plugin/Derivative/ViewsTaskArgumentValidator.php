<?php

namespace Drupal\task\Plugin\Derivative;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\Plugin\Derivative\ViewsEntityArgumentValidator;

/**
 * Provides views argument validator plugin definitions for all entity types.
 *
 * @ingroup views_argument_validator_plugins
 *
 * @see \Drupal\views\Plugin\views\argument_validator\Entity
 */
class ViewsTaskArgumentValidator extends ViewsEntityArgumentValidator {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $entity_type_id = 'user';
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $this->derivatives = [];
    $this->derivatives[$entity_type_id] = [
      'id' => 'entity:user',
      'provider' => 'views',
      'title' => $this->t('Task By User ID'),
      'help' => $this->t('Validate the @label ID allowed for the task.', ['@label' => $entity_type->getLabel()]),
      'entity_type' => 'task',
      'class' => $base_plugin_definition['class'],
    ];

    return $this->derivatives;
  }

}
