<?php

declare(strict_types = 1);

namespace Drupal\business_rules_additions\Plugin\BusinessRulesCondition;

use Drupal\business_rules\ConditionInterface;
use Drupal\business_rules\Events\BusinessRulesEvent;
use Drupal\business_rules\ItemInterface;
use Drupal\business_rules\Plugin\BusinessRulesConditionPlugin;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Condition to check whether the moderation state of an entity changes.
 *
 * @package Drupal\business_rules\Plugin\BusinessRulesCondition
 *
 * @\Drupal\business_rules\Annotation\BusinessRulesCondition(
 *   id = "moderation_state_changes_condition",
 *   label = @Translation("Moderation State Changes"),
 *   group = @Translation("Entity"),
 *   description = @Translation("Check if the moderation state changes."),
 *   isContextDependent = FALSE,
 *   reactsOnIds = {},
 * )
 */
class ModerationStateChangesCondition extends BusinessRulesConditionPlugin {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(
    array &$form,
    FormStateInterface $form_state,
    ItemInterface $item
  ) {
    $settings['moderation_state_changes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Moderation State Changes'),
      '#required' => FALSE,
      '#return_value' => 1,
      '#default_value' => $item->getSettings('moderation_state_changes'),
    ];

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function process(ConditionInterface $condition, BusinessRulesEvent $event) {
    $arguments = $event->getArguments();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $arguments['entity'];
    $entityUnchanged = $arguments['entity_unchanged'];
    if (!$entity->isNew()) {
      return $entity->get('moderation_state')->getValue() !== $entityUnchanged->get('moderation_state')->getValue();
    }
  }

}
