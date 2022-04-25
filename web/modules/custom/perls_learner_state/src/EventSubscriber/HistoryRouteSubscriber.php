<?php

namespace Drupal\perls_learner_state\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\perls_core\HistoryHelper;
use Drupal\perls_learner_state\PerlsLearnerStatementFlag;
use Drupal\perls_learner_state\Plugin\XapiStateManager;
use Drupal\perls_learner_state\UserAchievedGoalSubscriberBase;
use Drupal\xapi\Event\XapiStatementReceived;
use Drupal\xapi\LRSRequestGenerator;
use Drupal\xapi\XapiStatementHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Defines an event subscriber for history.read_node route.
 */
class HistoryRouteSubscriber implements EventSubscriberInterface {
  use UserAchievedGoalSubscriberBase;

  /**
   * Indicate that we reacted to trigger event.
   *
   * @var string
   */
  private $triggerEvent = '';

  /**
   * The state manager service.
   *
   * @var \Drupal\perls_learner_state\Plugin\XapiStateManager
   */
  private $stateManager;

  /**
   * The currently active route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatcher;

  /**
   * The cache invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheInvalidator;

  /**
   * The account of the current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The request generator service.
   *
   * @var \Drupal\xapi\LRSRequestGenerator
   */
  protected $requestGenerator;

  /**
   * Helper service to manage xapi statements.
   *
   * @var \Drupal\xapi\XapiStatementHelper
   */
  protected XapiStatementHelper $xapiStatementHelper;

  /**
   * Helper service to manage history data.
   *
   * @var \Drupal\perls_core\HistoryHelper
   */
  protected HistoryHelper $historyHelper;

  /**
   * Helper service which sync statement into a flag object.
   *
   * @var \Drupal\perls_learner_state\PerlsLearnerStatementFlag
   */
  protected PerlsLearnerStatementFlag $flagStatementHelper;

  /**
   * Subscriber to history read path.
   *
   * @param \Drupal\perls_learner_state\Plugin\XapiStateManager $state_manager
   *   The state manager service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_matcher
   *   The currently active route match object.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tag invalidator service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account of the current user.
   * @param \Drupal\xapi\LRSRequestGenerator $request_generator
   *   The request generator service.
   * @param \Drupal\xapi\XapiStatementHelper $xapi_statement_helper
   *   Helper service to manage xapi statements.
   * @param \Drupal\perls_core\HistoryHelper $historyHelper
   *   Helper service to manage history data.
   * @param \Drupal\perls_learner_state\PerlsLearnerStatementFlag $flag_statement_helper
   *   Helper service to sync statements with flag objects.
   */
  public function __construct(
    XapiStateManager $state_manager,
    RouteMatchInterface $route_matcher,
    CacheTagsInvalidatorInterface $cache_tags_invalidator,
    AccountInterface $account,
    LRSRequestGenerator $request_generator,
    XapiStatementHelper $xapi_statement_helper,
    HistoryHelper $historyHelper,
    PerlsLearnerStatementFlag $flag_statement_helper) {
    $this->stateManager = $state_manager;
    $this->routeMatcher = $route_matcher;
    $this->cacheInvalidator = $cache_tags_invalidator;
    $this->currentUser = $account;
    $this->requestGenerator = $request_generator;
    $this->xapiStatementHelper = $xapi_statement_helper;
    $this->historyHelper = $historyHelper;
    $this->flagStatementHelper = $flag_statement_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[KernelEvents::FINISH_REQUEST][] = ['historyRoute', 30];
    $events[XapiStatementReceived::EVENT_NAME][] = [
      'getHistoryStatement',
      -100,
    ];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function historyRoute(KernelEvent $event) {
    // Don't process events with HTTP exceptions - those have either been thrown
    // by us or have nothing to do with rabbit hole.
    if ($event->getRequest()->get('exception') != NULL) {
      return;
    }

    // Get the route from the request.
    if ($route = $event->getRequest()->get('_route')) {
      // Only continue if the request route a history view route.
      if ($route === 'history.read_node' && empty($this->triggerEvent)) {
        $this->triggerEvent = 'drupal';
        $node = $this->routeMatcher->getParameter('node');
        $this->addHistoryContent($node);
        // We reset the triggerEvent property because we are over
        // XapiStatementReceived event.
        $this->triggerEvent = '';
      }
    }
  }

  /**
   * Add history event to drupal.
   *
   * @param \Drupal\xapi\Event\XapiStatementReceived $event
   *   An event object which contains a statement/s.
   */
  public function getHistoryStatement(XapiStatementReceived $event) {
    if (empty($this->triggerEvent)) {
      $this->triggerEvent = 'mobile';
      $history_verbs = $this->getAllHistoryVerb();
      $statement = $event->getStatement();
      // Validate the entity and user in the xAPI statement.
      if ($this->xapiStatementHelper->validate($statement)) {
        $flag_data = $this->flagStatementHelper->getFlagOperationFromStatement($statement);
        $entity = $this->xapiStatementHelper->getContentFromState($statement);

        // Check if the entity is of type node and the verb_urls match the list
        // of verbs related to history xAPI statements.
        if ($entity instanceof NodeInterface && isset($flag_data['verb_url']) && in_array($flag_data['verb_url'], $history_verbs)) {
          $this->historyHelper->historySync($statement);
        }
      }
      $this->triggerEvent = '';
    }
  }

  /**
   * Create a xapi_seen_state when a history event occurs in drupal.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function addHistoryContent(NodeInterface $node) {
    $history_record_time = history_read($node->id());
    // Invalidate all history related cache.
    $this->invalidateCache();
    $this->stateManager->sendStatement('xapi_seen_state', $node, NULL, $history_record_time);

    // Trigger a goal check.
    $this->addItemQueue([
      'goal_type' => 'viewed',
      'user' => $this->currentUser->id(),
    ]);
  }

  /**
   * Invalidates the proper cache tags.
   */
  public function invalidateCache() {
    $cache_tags = [
      "user_history:{$this->currentUser->id()}",
    ];

    $this->cacheInvalidator->invalidateTags($cache_tags);
  }

  /**
   * Gives back all verb url which are related to history module.
   *
   * There are more statement type which we need to sync with history records.
   * This collect all xapi state plugin type which are related to history
   * records. Load all of them and take out their verb urls.
   *
   * @return array
   *   A list of urls of xapi verbs.
   */
  public function getAllHistoryVerb() {
    $state_plugin_list = [
      'xapi_seen_state',
      'xapi_viewed_state',
    ];

    $state_urls = [];

    foreach ($state_plugin_list as $plugin_id) {
      $plugin = $this->stateManager->getDefinition($plugin_id);
      $state_urls[] = $plugin['add_verb']->getId();
    }

    return $state_urls;
  }

}
