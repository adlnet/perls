<?php

declare(strict_types = 1);

namespace Drupal\business_rules_additions\Plugin\BusinessRulesAction;

use Drupal\business_rules\ActionInterface;
use Drupal\business_rules\Events\BusinessRulesEvent;

/**
 * Business Rules Plugin to demote an entity from the front page.
 *
 * @package Drupal\business_rules_additions\Plugin\BusinessRulesAction
 *
 * @\Drupal\business_rules\Annotation\BusinessRulesAction(
 *   id = "demote_from_front_page_action",
 *   label = @Translation("Demote from front page"),
 *   group = @Translation("Entity"),
 *   description = @Translation("Demote selected content from front page."),
 *   isContextDependent = FALSE,
 *   hasTargetEntity = TRUE,
 *   hasTargetBundle = TRUE,
 *   hasTargetField = TRUE,
 * )
 */
class DemoteFromFrontPageAction extends FrontPageAction {

  /**
   * {@inheritdoc}
   */
  public function execute(
    ActionInterface $action,
    BusinessRulesEvent $event
  ): array {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $event->getSubject();
    if (!$entity->hasField('promote')) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('The entity with id: %ids cannot be demoted from the front page.', [
          '%ids' => $entity->id(),
        ]),
      ];
    }
    $entity
      ->set('promote', 0)
      ->save();

    return [
      '#type' => 'markup',
      '#markup' => $this->t('The entity is demoted from the front page with id: %ids.', [
        '%ids' => $entity->id(),
      ]),
    ];
  }

}
