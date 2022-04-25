<?php

namespace Drupal\perls_learner_state\EventSubscriber;

use Drupal\node\NodeInterface;
use Drupal\perls_learner_state\Plugin\XapiStateManager;
use Drupal\content_moderation_additions\Event\ModerationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles tracking changes of moderation state of content.
 */
class ModerationStateSubscriber implements EventSubscriberInterface {


  /**
   * Service for getting xapi state plugins.
   *
   * @var \Drupal\perls_learner_state\Plugin\XapiStateManager
   */
  protected $stateManager;

  /**
   * Constructs a new ModerationStateSubscriber object.
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
    $events['content_moderation_additions.content_moderation_state.update'] = ['handleModerationEvent'];

    return $events;
  }

  /**
   * Sends Xapi statments about moderation state.
   *
   * When moderation state of an article changes this sends the appropriate
   * xapi statements.
   *
   * @param \Drupal\content_moderation_additions\Event\ModerationEvent $event
   *   An event which contains the entity with new moderation state.
   */
  public function handleModerationEvent(ModerationEvent $event) {
    $entity = $event->getEntity();
    // For now focus only on nodes.
    if (!$entity instanceof NodeInterface) {
      return;
    }
    $moderation_state = $entity->moderation_state->value;

    switch ($moderation_state) {
      case 'review':
        $this->stateManager->sendStatement('xapi_content_submitted_state', $entity);
        break;

      case 'published':
        $this->stateManager->sendStatement('xapi_content_published_state', $entity);
        $this->stateManager->sendStatement('xapi_content_contributed_state', $entity, $entity->getOwner());
        break;

      case 'archived':
        $this->stateManager->sendStatement('xapi_content_archived_state', $entity);
        break;
    }
  }

}
