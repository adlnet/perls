<?php

declare(strict_types = 1);

namespace Drupal\business_rules_additions\Plugin\BusinessRulesAction;

use Drupal\business_rules\ActionInterface;
use Drupal\business_rules\Events\BusinessRulesEvent;

/**
 * Business Rules Plugin to promote an entity to the front page.
 *
 * @package Drupal\business_rules_additions\Plugin\BusinessRulesAction
 *
 * @\Drupal\business_rules\Annotation\BusinessRulesAction(
 *   id = "promote_to_front_page_action",
 *   label = @Translation("Promote to front page"),
 *   group = @Translation("Entity"),
 *   description = @Translation("Promote selected content to front page."),
 *   isContextDependent = FALSE,
 *   hasTargetEntity = TRUE,
 *   hasTargetBundle = TRUE,
 *   hasTargetField = TRUE,
 * )
 */
class PromoteToFrontPageAction extends FrontPageAction {

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
        '#markup' => $this->t('The entity with id: %id cannot be promoted to the front page.', [
          '%id' => $entity->id(),
        ]),
      ];
    }
    $entity
      ->set('promote', 1)
      ->save();

    return [
      '#type' => 'markup',
      '#markup' => $this->t('The entity is promoted to the front page with id: %id.', [
        '%id' => $entity->id(),
      ]),
    ];
  }

}
