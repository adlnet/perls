<?php

namespace Drupal\content_moderation_additions;

use Drupal\content_moderation\Entity\ContentModerationState as ContentModerationStateEntity;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\node\NodeInterface;
use Drupal\content_moderation_additions\Event\ModerationEvent;

/**
 * Handles getting and setting of moderation reviewers.
 */
class ModerationNodeReviewer implements ModerationNodeReviewerInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Module handler services definitions.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * ModerationNodeReviewer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(EntityTypeManager $entityTypeManager, ModuleHandlerInterface $moduleHandler) {
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Retrieves an array of user IDs that are eligible to review this entity.
   */
  public function getValidReviewers(ContentEntityInterface $entity) {
    $context = [
      'entity' => clone $entity,
    ];

    $query = $this->entityTypeManager->getStorage('user')->getQuery();
    $this->moduleHandler->alter('content_moderation_additions_reviewers_query', $query, $context);

    $uids = $query->execute();
    $this->moduleHandler->alter('content_moderation_additions_reviewers', $uids, $context);

    return $uids;
  }

  /**
   * Check to see if reviewer is a valid choice for this entity.
   */
  public function isValidReviewer(ContentEntityInterface $entity, $reviewer_id) {
    return in_array($reviewer_id, $this->getValidReviewers($entity));
  }

  /**
   * Retrieves the currently assigned reviewer.
   */
  public function getCurrentReviewer(ContentEntityInterface $entity) {
    $current_state = $this->getModerationState($entity);
    return ($current_state && $current_state->field_reviewer) ? $current_state->field_reviewer->target_id : '';
  }

  /**
   * Assigns the content to a reviewer.
   */
  public function setCurrentReviewer(ContentEntityInterface $entity, $reviewer_id) {
    if ($reviewer_id === $this->getCurrentReviewer($entity)) {
      return;
    }

    $this->setModerationStateValue($entity, 'field_reviewer', ['target_id' => $reviewer_id]);
    \Drupal::service('event_dispatcher')->dispatch(ModerationEvent::SL_MODERATION_REVIEWER_UPDATE, new ModerationEvent($entity));

    // Make sure the new reviewer has access to view the node.
    if (!$entity instanceof NodeInterface) {
      return;
    }

    $access_control_handler = \Drupal::entityTypeManager()->getAccessControlHandler($entity->getEntityTypeId());
    if (!$access_control_handler) {
      return;
    }

    $grants = $access_control_handler->acquireGrants($entity);
    \Drupal::service('node.grant_storage')->write($entity, $grants, ModerationAccess::GRANT_REALM);
  }

  /**
   * Sets a value on the current moderation state for the entity.
   */
  private function setModerationStateValue(ContentEntityInterface $entity, $field, $value) {
    $state = $this->getModerationState($entity);

    if ($state instanceof ContentModerationStateEntity) {
      $state->$field = $value;
      ContentModerationStateEntity::updateOrCreateFromEntity($state);
    }
  }

  /**
   * Retrieves a value from the current moderation state.
   */
  private function getModerationState(ContentEntityInterface $entity) {
    return ContentModerationStateEntity::loadFromModeratedEntity($entity);
  }

}
