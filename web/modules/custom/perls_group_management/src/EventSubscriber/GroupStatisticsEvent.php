<?php

namespace Drupal\perls_group_management\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidator;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\flag\Event\FlagEvents;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\Event\UnflaggingEvent;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\perls_learner_state\PerlsLearnerStatementFlag;
use Drupal\xapi\Event\XapiStatementReceived;
use Drupal\user\Entity\User;
use Drupal\xapi\XapiStatementHelper;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listening to group statistics related events to invalidate the cache.
 */
class GroupStatisticsEvent implements EventSubscriberInterface {

  /**
   * The group membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $membershipLoader;

  /**
   * Cache tag invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidator
   */
  protected $cacheInvalidator;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The statement helper service.
   *
   * @var \Drupal\xapi\XapiStatementHelper
   */
  protected $xapiStatementHelper;

  /**
   * Indicate that we reacted to trigger event.
   *
   * @var string
   */
  private $triggerEvent = '';

  /**
   * Statement flaf helper service from perls_learner_state module.
   *
   * @var \Drupal\perls_learner_state\PerlsLearnerStatementFlag
   */
  protected PerlsLearnerStatementFlag $perlsLearnerStateHelper;

  /**
   * GroupFlaggingEvent constructor.
   *
   * @param \Drupal\group\GroupMembershipLoaderInterface $membership_loader
   *   The group membership loader.
   * @param \Drupal\Core\Cache\CacheTagsInvalidator $cache_invalidator
   *   The cache tag invalidator service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\perls_learner_state\PerlsLearnerStatementFlag $flag_helper
   *   The statement helper service.
   * @param \Drupal\xapi\XapiStatementHelper $xapi_statement_helper
   *   The statement helper service.
   */
  public function __construct(
    GroupMembershipLoaderInterface $membership_loader,
    CacheTagsInvalidator $cache_invalidator,
    AccountProxyInterface $current_user,
    PerlsLearnerStatementFlag $flag_helper,
    XapiStatementHelper $xapi_statement_helper) {
    $this->membershipLoader = $membership_loader;
    $this->cacheInvalidator = $cache_invalidator;
    $this->currentUser = $current_user;
    $this->perlsLearnerStateHelper = $flag_helper;
    $this->xapiStatementHelper = $xapi_statement_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[FlagEvents::ENTITY_FLAGGED][] = ['groupContentFlagged', -100];
    $events[FlagEvents::ENTITY_UNFLAGGED][] = ['groupContentFlagged', -100];
    $events[XapiStatementReceived::EVENT_NAME][] = [
      'groupStatementContent',
      -100,
    ];

    return $events;
  }

  /**
   * Clear cache tags on those groups where a member user flagged a content.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The event object.
   */
  public function groupContentFlagged(Event $event) {
    if (empty($this->triggerEvent)) {
      if ($event instanceof FlaggingEvent) {
        /** @var \Drupal\flag\Entity\Flagging $flagging */
        $flagging = $event->getFlagging();
      }
      elseif ($event instanceof UnflaggingEvent) {
        $flaggings = $event->getFlaggings();
        $flagging = reset($flaggings);
      }

      $user = $flagging->getOwnerId();
      $this->invalidateGroupCache($user);
      $this->triggerEvent = 'drupal';
    }
  }

  /**
   * Invalidate the group cache if the statement has flag related content.
   *
   * @param \Drupal\xapi\Event\XapiStatementReceived $event
   *   An event.
   */
  public function groupStatementContent(XapiStatementReceived $event) {
    if (empty($this->triggerEvent)) {
      $statement = $event->getStatement();
      $flag_operation = $this->perlsLearnerStateHelper->getFlagOperationFromStatement($statement);
      if ((isset($flag_operation['flag_plugins']) &&
          !empty($flag_operation['flag_plugins'])) ||
        (isset($flag_operation['verb_url']) &&
          $flag_operation['verb_url'] === 'http://adlnet.gov/expapi/verbs/launched')) {
        $user = $this->xapiStatementHelper->getUserFromStatement($statement);
        if ($user) {
          $this->invalidateGroupCache($user->id());
        }
      }
    }
  }

  /**
   * Gives back all group id where the user is member.
   *
   * @param int $user_id
   *   User id.
   *
   * @return array
   *   List of user ids.
   */
  private function getUserGroups($user_id) {
    $group_list = [];
    $user = User::load($user_id);
    foreach ($this->membershipLoader->loadByUser($user) as $group_membership) {
      $group_list[] = $group_membership->getGroup()->id();
    }

    return $group_list;
  }

  /**
   * Invalidate the cache in those groups where the user is member.
   *
   * @param int $user_id
   *   The user id.
   */
  private function invalidateGroupCache($user_id) {
    $group_list = $this->getUserGroups($user_id);
    $tags = [];
    foreach ($group_list as $group_id) {
      $tags[] = 'group:' . $group_id;
    }

    $this->cacheInvalidator->invalidateTags($tags);
  }

}
