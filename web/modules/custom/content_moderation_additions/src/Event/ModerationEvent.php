<?php

namespace Drupal\content_moderation_additions\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Represents a change in moderation for an entity.
 */
class ModerationEvent extends Event {

  const SL_MODERATION_STATE_UPDATE = 'content_moderation_additions.content_moderation_state.update';
  const SL_MODERATION_REVIEWER_UPDATE = 'content_moderation_additions.content_moderation_reviewer.update';

  /**
   * Node entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Constructs a node insertion event object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The node being inserted.
   */
  public function __construct(EntityInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * Get the inserted entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The node inserted.
   */
  public function getEntity() {
    return $this->entity;
  }

}
