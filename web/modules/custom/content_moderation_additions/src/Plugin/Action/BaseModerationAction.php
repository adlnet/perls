<?php

namespace Drupal\content_moderation_additions\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides the base implementation to update nodes to a new moderation state.
 */
abstract class BaseModerationAction extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;

  /**
   * Returns the target moderation state ID (e.g. 'archived').
   */
  abstract protected function getTargetStateId();

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $workflow = $this->getWorkflow($entity);

    if ($workflow == NULL) {
      return $this->t('No workflow found');
    }

    $state = $workflow->getTypePlugin()->getState($this->getTargetStateId());

    if ($entity->moderation_state->value != $state->id()) {
      $entity->setNewRevision(TRUE);
      $entity->setRevisionLogMessage($this->t('Performing bulk update to move articles to :state', [':state' => $state->label()]));
      $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
      $entity->setRevisionUserId(\Drupal::currentUser()->id());
      $entity->moderation_state = $state->id();
      $entity->save();
    }

    return $this->t('Moved to :state', [':state' => $state->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $workflow = $this->getWorkflow($object);
    if ($workflow == NULL) {
      return AccessResult::forbidden();
    }

    $currentState = $workflow->getTypePlugin()->getState($object->moderation_state->value);

    try {
      $transition = $currentState->getTransitionTo($this->getTargetStateId());
    }
    catch (\Exception $e) {
      return AccessResult::forbidden();
    }

    return $account->hasPermission('use ' . $workflow->id() . ' transition ' . $transition->id());
  }

  /**
   * Retrieves the workflow for the specified entity.
   *
   * @param object $entity
   *   A moderated entity.
   *
   * @return Drupal\workflows\Entity\Workflow
   *   The workflow for the entity.
   */
  protected function getWorkflow($entity) {
    $moderation_info = \Drupal::service('content_moderation.moderation_information');
    return $moderation_info->getWorkflowForEntity($entity);
  }

}
