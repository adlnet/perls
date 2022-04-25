<?php

namespace Drupal\perls_learner_state\EventSubscriber;

use Drupal\flag\Event\FlagEvents;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\flag\Event\UnflaggingEvent;
use Drupal\Core\Entity\EntityInterface;
use Drupal\perls_learner_state\PerlsLearnerStatementFlag;
use Drupal\xapi\XapiStatementHelper;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\xapi\XapiStatement;
use Drupal\perls_learner_state\Plugin\XapiStateBase;
use Drupal\perls_learner_state\Plugin\XapiStateManager;
use Drupal\xapi\LRSRequestGenerator;
use Drupal\perls_learner_state\UserAchievedGoalSubscriberBase;
use Drupal\xapi\Event\XapiStatementReceived;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * This event subscriber listen for flagging and unflagging event.
 */
class FlagEventSubscriber implements EventSubscriberInterface {
  use UserAchievedGoalSubscriberBase;

  /**
   * This is a list of flagged content which are in progress.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  private $flaggedContentEntityList = [];

  /**
   * The stage manager service.
   *
   * @var \Drupal\perls_learner_state\Plugin\XapiStateManager
   */
  protected $stateManager;

  /**
   * The current path service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The route provider service.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * A request generator service.
   *
   * @var \Drupal\xapi\LRSRequestGenerator
   */
  protected $requestGenerator;

  /**
   * Statement helper class.
   *
   * @var \Drupal\perls_learner_state\PerlsLearnerStatementFlag
   */
  protected $flagStatementHelper;

  /**
   * Statement helper service from xapi module.
   *
   * @var \Drupal\xapi\XapiStatementHelper
   */
  protected XapiStatementHelper $xapiStatementHelper;

  /**
   * Flagging subscriber.
   *
   * @param \Drupal\perls_learner_state\Plugin\XapiStateManager $state_manager
   *   The state manager api.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider service.
   * @param \Drupal\xapi\LRSRequestGenerator $request_generator
   *   A service to generate proper post request to statement endpoint.
   * @param \Drupal\perls_learner_state\PerlsLearnerStatementFlag $flag_helper
   *   A statement helper class.
   * @param \Drupal\xapi\XapiStatementHelper $xapi_statement_helper
   *   Helper service from xapi module.
   */
  public function __construct(
    XapiStateManager $state_manager,
    CurrentPathStack $current_path,
    RouteProviderInterface $route_provider,
    LRSRequestGenerator $request_generator,
    PerlsLearnerStatementFlag $flag_helper,
    XapiStatementHelper $xapi_statement_helper) {
    $this->stateManager = $state_manager;
    $this->currentPath = $current_path;
    $this->routeProvider = $route_provider;
    $this->requestGenerator = $request_generator;
    $this->flagStatementHelper = $flag_helper;
    $this->xapiStatementHelper = $xapi_statement_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[FlagEvents::ENTITY_FLAGGED] = [
      ['drupalBaseFlagEvent', -100],
      ['checkUserGoal'],
    ];
    $events[FlagEvents::ENTITY_UNFLAGGED][] = ['drupalBaseFlagEvent', -100];
    $events[XapiStatementReceived::EVENT_NAME] = [
      ['xapiBaseStateEvent', -100],
    ];
    return $events;
  }

  /**
   * This function react for flagging event.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The event object.
   */
  public function drupalBaseFlagEvent(Event $event) {
    $flagging = NULL;
    if ($event instanceof FlaggingEvent) {
      $flagging = $event->getFlagging();
    }
    elseif ($event instanceof UnflaggingEvent) {
      /** @var \Drupal\flag\Entity\Flagging $flagging */
      $all_flagging = $event->getFlaggings();
      $flagging = reset($all_flagging);
    }

    // We try to prevent that we create infinite loop. This class we listen
    // to two different events the flagging one and the XapiStatementReceived.
    // We receive XapiStatementReceived usually when the app send statement.
    // When we got a new statement from app we try to save that data in drupal
    // (create new flag) which trigger the flagging event where we doesn't want
    // to create a new statement.(same)
    // There is an another case when we complete a course content then we
    // check that all course content is completed because then we need to set
    // completed status for course, which trigger a new flagged event.
    // We check the isActiveRequest function that an active flagging event
    // triggered a new one.
    if (isset($flagging) && $this->isActiveRequest($flagging->getFlaggable())) {
      return;
    }

    $this->flaggedContentEntityList[$flagging->getFlaggable()->id()] = $flagging->getFlaggable();
    $actual_flag = $flagging->getFlag();
    $stage_definitions = $this->stateManager->getDefinitions();
    $statements = [];
    foreach ($stage_definitions as $plugin_id => $definition) {
      if ($definition['flag'] === $actual_flag->id()) {
        if (!empty($this->stateManager->getDefinition($plugin_id)['add_verb'])) {
          /** @var \Drupal\perls_learner_state\Plugin\XapiStateBase $stage */
          $stage = $this->stateManager->createInstance($plugin_id);

          if ($event instanceof UnflaggingEvent) {
            if (empty($this->stateManager->getDefinition($plugin_id)['remove_verb'])) {
              // If unflagging and remove verb is null skip to next plugin.
              continue;
            }
            $stage->setOperation(XapiStateBase::OPERATION_REMOVE);
          }

          $statement = $stage->prepareStatementFromFlag($flagging);
          // Not all plugins return a statement,
          // some require certain entity types.
          if ($statement instanceof XapiStatement) {
            $statements[] = $statement;
          }
        }
      }
    }
    if (!empty($statements)) {
      $this->requestGenerator->sendStatements($statements);
      unset($this->flaggedContentEntityList[$flagging->getFlaggable()->id()]);
    }
  }

  /**
   * The flag event is coming from mobile app, we need to sync.
   *
   * @param \Drupal\xapi\Event\XapiStatementReceived $event
   *   An event when somebody sent a request to statement endpoint.
   */
  public function xapiBaseStateEvent(XapiStatementReceived $event) {
    $statement = $event->getStatement();
    $entity = $this->xapiStatementHelper->getContentFromState($statement);
    $flag_operation = $this->flagStatementHelper->getFlagOperationFromStatement($statement);
    if (isset($entity) && !$this->isActiveRequest($entity) && !empty($flag_operation['flag_plugins']) && $this->xapiStatementHelper->validate($statement)) {
      $this->flaggedContentEntityList[$entity->id()] = $entity;
      $this->flagStatementHelper->flagStatementSync($statement);
      unset($this->flaggedContentEntityList[$entity->id()]);
    }
  }

  /**
   * Add a goal check element to queue.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   A flag event.
   */
  public function checkUserGoal(Event $event) {
    if ($event instanceof FlaggingEvent) {
      $flagging = $event->getFlagging();
      $flag = $flagging->getFlag();
      if ($flag->id() === 'completed') {
        // Trigger a goal check.
        $this->addItemQueue([
          'goal_type' => 'completed',
          'user' => $flagging->getOwner()->id(),
        ]);
      }
    }
  }

  /**
   * Check that is our previous request triggered this request.
   *
   * @param \Drupal\Core\Entity\EntityInterface $current_entity
   *   Currently flagged content.
   *
   * @return bool
   *   A TRUE or FALSE.
   */
  protected function isActiveRequest(EntityInterface $current_entity) {
    return (isset($current_entity) && !empty($this->flaggedContentEntityList) && (int) $current_entity->id() === array_key_last($this->flaggedContentEntityList));
  }

}
