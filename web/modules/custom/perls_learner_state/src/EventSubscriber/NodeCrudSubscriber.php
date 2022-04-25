<?php

namespace Drupal\perls_learner_state\EventSubscriber;

use Drupal\node\NodeInterface;
use Drupal\perls_learner_state\Event\NodeCrudEvent;
use Drupal\perls_learner_state\Plugin\XapiStateManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to node crud events.
 */
class NodeCrudSubscriber implements EventSubscriberInterface {

  /**
   * Service for getting xapi state plugins.
   *
   * @var \Drupal\perls_learner_state\Plugin\XapiStateManager
   */
  protected $stateManager;

  /**
   * Constructs a new NodeCrudSubscriber object.
   *
   * @param \Drupal\perls_learner_state\Plugin\XapiStateManager $state_manager
   *   The statement state plugin manager.
   */
  public function __construct(XapiStateManager $state_manager) {
    $this->stateManager = $state_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[NodeCrudEvent::EVENT_NAME] = ['handleNodeCrudEvent'];

    return $events;
  }

  /**
   * Sends Xapi statments about node crud events.
   *
   * @param \Drupal\perls_learner_state\Event\NodeCrudEvent $event
   *   An event which contains the entity and operation of the update.
   */
  public function handleNodeCrudEvent(NodeCrudEvent $event) {
    $entity = $event->getEntity();
    $operation = $event->getOperation();
    // For now focus only on nodes.
    if (!$entity instanceof NodeInterface || $operation === '') {
      return;
    }

    switch ($operation) {
      case NodeCrudEvent::CREATE:
        $this->stateManager->sendStatement('xapi_content_created_state', $entity);
        break;

      case NodeCrudEvent::UPDATE:
        $this->stateManager->sendStatement('xapi_content_updated_state', $entity);
        break;

      case NodeCrudEvent::DELETE:
        $this->stateManager->sendStatement('xapi_content_deleted_state', $entity);
        break;
    }
  }

}
