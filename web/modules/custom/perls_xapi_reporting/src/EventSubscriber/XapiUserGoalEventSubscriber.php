<?php

namespace Drupal\perls_xapi_reporting\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\core_event_dispatcher\Event\Entity\EntityDeleteEvent;
use Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent;
use Drupal\core_event_dispatcher\Event\Entity\EntityUpdateEvent;
use Drupal\perls_xapi_reporting\PerlsXapiVerb;
use Drupal\perls_content\EntityUpdateChecker;
use Drupal\xapi\LRSRequestGenerator;
use Drupal\xapi\XapiActivity;
use Drupal\xapi\XapiStatement;
use Drupal\xapi_reporting\XapiStatementCreator;
use Drupal\task\Entity\TaskInterface;
use Drupal\user\UserInterface;

/**
 * An event subscriber which catch the event when user achieved a goal.
 */
class XapiUserGoalEventSubscriber extends BaseEntityCrudSubscriber {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Service to check entity is modified.
   *
   * @var \Drupal\perls_content\EntityUpdateChecker
   */
  protected $entityUpdateChecker;

  /**
   * Constructs a new XapiUserGoalEventSubscriber object.
   *
   * @param \Drupal\xapi\LRSRequestGenerator $request_generator
   *   The entity update checker.
   * @param \Drupal\xapi_reporting\XapiStatementCreator $statement_creator
   *   The entity update checker.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current user.
   * @param \Drupal\perls_content\EntityUpdateChecker $entity_update_checker
   *   The entity update checker.
   */
  public function __construct(
    LRSRequestGenerator $request_generator,
    XapiStatementCreator $statement_creator,
    AccountProxyInterface $current_user,
    EntityUpdateChecker $entity_update_checker) {
    parent::__construct($request_generator, $statement_creator);
    $this->currentUser = $current_user;
    $this->entityUpdateChecker = $entity_update_checker;
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsEntity(EntityInterface $entity): bool {
    return $entity instanceof TaskInterface;
  }

  /**
   * {@inheritdoc}
   */
  protected function onEntityInserted(EntityInsertEvent $event) {
    /** @var \Drupal\task\Entity\Task $entity */
    $entity = $event->getEntity();
    $userId = $this->getOwner($entity);
    $user = $entity->get('user_id')->entity;
    $statement = $this->createTaskStatement($entity, $user)
      ->setVerb(PerlsXapiVerb::defined());
    // Send xapi statement as new user if no session exists.
    $this->sendStatement($statement, $userId);
  }

  /**
   * {@inheritdoc}
   */
  protected function onEntityUpdated(EntityUpdateEvent $event) {
    /** @var \Drupal\task\Entity\Task $entity */
    $entity = $event->getEntity();
    $user = $entity->get('user_id')->entity;
    // Skip EntityUpdated statement if the user is new, or there is no change
    // in the user profile.
    if (!$this->currentUser->isAuthenticated() ||
      !$this->entityUpdateChecker->isAltered($entity,
        ['name', 'completion_date'])) {
      return;
    }
    $statement = $this->createTaskStatement($entity, $user)
      ->setVerb($entity->isComplete() ? PerlsXapiVerb::completed() : PerlsXapiVerb::defined());
    $this->sendStatement($statement, $user->id());
  }

  /**
   * {@inheritdoc}
   */
  protected function onEntityDeleted(EntityDeleteEvent $event) {
    /** @var \Drupal\task\Entity\Task $entity */
    $entity = $event->getEntity();
    $userId = $this->getOwner($entity);
    $user = $entity->get('user_id')->entity;
    $statement = $this->createTaskStatement($entity, $user)
      ->setVerb(PerlsXapiVerb::cancelled());
    $this->sendStatement($statement, $userId);
  }

  /**
   * Helper method to get the owner of Task entity.
   *
   * @param mixed $entity
   *   Entity object.
   *
   * @return mixed
   *   User ID.
   */
  private function getOwner($entity) {
    return $entity->get('user_id')->target_id;
  }

  /**
   * Definition of the custom goal extension.
   *
   * @return string[]
   *   Returns extension array.
   */
  private function customGoalExtension() {
    return ["http://xapi.gowithfloat.net/extension/goal-type" => "custom"];
  }

  /**
   * Gets the activity name for the current goal.
   *
   * @param \Drupal\task\Entity\TaskInterface $entity
   *   The entity that was either created, updated, or deleted.
   *
   * @return string
   *   The activity name.
   */
  private function getGoalName(TaskInterface $entity): string {
    /** @var \Drupal\field\Entity\FieldConfig $goal_field_config */
    return 'a custom goal to ' . strtolower($entity->getName());
  }

  /**
   * Creates a statement for create/update task events.
   *
   * @param \Drupal\task\Entity\TaskInterface $entity
   *   The task that was created/updated.
   * @param \Drupal\Core\UserInterface $user
   *   The user who created/updated the task.
   *
   * @return \Drupal\xapi\XapiStatement
   *   The statement that was generated with the task/user info.
   */
  private function createTaskStatement(TaskInterface $entity, UserInterface $user) : XapiStatement {
    /** @var \Drupal\task\Entity\Task $entity */
    $statement = parent::createStatement($entity)
      ->addObjectExtensions($this->customGoalExtension());
    $object = $statement->getObject();
    $object->setName($this->getGoalName($entity));
    $statement->setActivity($object);
    /** @var \Drupal\Core\Entity\EntityInterface $user */
    $parent_activity = XapiActivity::createFromEntity($user);
    $statement->addParentContext($parent_activity);
    return $statement;
  }

}
