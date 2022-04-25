<?php

namespace Drupal\perls_content_management\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_clone\Event\EntityCloneEvent;
use Drupal\entity_clone\Event\EntityCloneEvents;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Catch the clone event and does changes when we clone a content.
 */
class ContentCloneSubscriber implements EventSubscriberInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Node type definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|null
   */
  protected $nodeDefinition;

  /**
   * ContentCloneSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Drupal entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->nodeDefinition = $entity_type_manager->getDefinition('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[EntityCloneEvents::POST_CLONE][] = ['postClone'];
    return $events;
  }

  /**
   * React to clone event after the cms saved the new cloned content.
   *
   * @param \Drupal\entity_clone\Event\EntityCloneEvent $event
   *   The cloned event.
   */
  public function postClone(EntityCloneEvent $event) {
    $cloned_entity = $event->getClonedEntity();

    if (($cloned_entity instanceof Node) &&
      $cloned_entity->bundle() === 'course' &&
      $cloned_entity->hasField('field_learning_content')) {
      $learning_ref_field = $cloned_entity->get('field_learning_content')->referencedEntities();
      if (empty($learning_ref_field)) {
        return;
      }

      $learning_contents = [];
      foreach ($learning_ref_field as $id => $entity) {
        if (($entity instanceof Node) && $entity->bundle() === 'test') {
          $temp_entity = clone $entity;
          // We need to set the proper cer reference to the new course otherwise
          // the new cloned test will appear at the original course as well.
          if ($temp_entity->hasField('field_test_course')) {
            $temp_entity->set('field_test_course', $cloned_entity->id());
          }
          $cloned_test = $this->cloneEntity($temp_entity);
          $learning_contents[] = $cloned_test->id();
        }
        else {
          $learning_contents[] = $entity->id();
        }
      }
      $cloned_entity->set('field_learning_content', $learning_contents);
      $cloned_entity->save();
    }
  }

  /**
   * Clone a node entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The original node.
   */
  protected function cloneEntity(EntityInterface $entity): EntityInterface {
    $entity_clone_handler = $this->entityTypeManager->getHandler($this->nodeDefinition->id(), 'entity_clone');
    $duplicate = $entity->createDuplicate();
    /** @var \Drupal\entity_clone\EntityClone\EntityCloneInterface $entity_clone_handler */
    return $entity_clone_handler->cloneEntity($entity, $duplicate);
  }

}
