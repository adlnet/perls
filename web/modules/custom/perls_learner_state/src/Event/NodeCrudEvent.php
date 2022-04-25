<?php

namespace Drupal\perls_learner_state\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Represents a change node.
 */
class NodeCrudEvent extends Event {

  const EVENT_NAME = 'perls_learner_state.node_crud_events.event';
  const CREATE = 'create';
  const UPDATE = 'update';
  const DELETE = 'delete';

  /**
   * Node entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The Event operation.
   *
   * @var string
   */
  protected $operation;

  /**
   * Constructs a node CRUD event object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The node being Created/Updated/Deleted.
   * @param string $operation
   *   Is the Entity being created, updated, deleted.
   */
  public function __construct(EntityInterface $entity, $operation = self::CREATE) {
    $this->entity = $entity;
    $this->operation = $operation;
  }

  /**
   * Get the node entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The node.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Get the operation.
   */
  public function getOperation() {
    return $this->operation;
  }

}
