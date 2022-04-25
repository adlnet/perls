<?php

namespace Drupal\badges_xapi_reporting\EventSubscriber;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\badges\Service\BadgeService;
use Drupal\xapi\Event\XapiStatementReceived;
use Drupal\xapi\XapiStatementHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class DailyStreakXApiStatementSubscriber.
 *
 * @package Drupal\badges\EventSubscriber
 */
class BadgeXApiStatementSubscriber implements EventSubscriberInterface {

  /**
   * Statement helper class.
   *
   * @var \Drupal\xapi\XapiStatementHelper
   */
  protected $statementHelper;
  /**
   * Badge plugin manager.
   *
   * @var \Drupal\badges\Service\BadgeService
   */
  protected $badgeService;

  /**
   * The entity repository helper.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  public $entityRepository;

  /**
   * Subscriber to Xapi statements.
   *
   * @param \Drupal\badges\Service\BadgeService $badgeService
   *   Badge Service class.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\xapi\XapiStatementHelper $statement_helper
   *   The xapi statement helper.
   */
  public function __construct(BadgeService $badgeService, EntityRepositoryInterface $entity_repository, XapiStatementHelper $statement_helper) {
    $this->entityRepository = $entity_repository;
    $this->badgeService = $badgeService;
    $this->statementHelper = $statement_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[XapiStatementReceived::EVENT_NAME][] = ['xapiStatementReceived'];
    return $events;
  }

  /**
   * Update Daily streak and time based badges.
   *
   * @param \Drupal\xapi\Event\XapiStatementReceived $event
   *   An event object which contains a statement/s.
   */
  public function xapiStatementReceived(XapiStatementReceived $event) {
    $statement = $event->getStatement();
    $user = $this->statementHelper->getUserFromStatement($statement);
    $time = $this->getTimestampFromStatement($statement);
    $verb = $this->getVerbFromStatement($statement);
    $activity = $this->getActivityFromStatement($statement);

    // Streak badges only updated on following events.
    if (is_null($time)
      || is_null($user)
      || !in_array($verb['display'],
            [
              'completed',
              'launched',
              'opened',
              'closed',
              'searched',
              'viewed',
            ]
          )
      ) {
      return;
    }
    // Update daily streak badges.
    if ($daily_streak_badge_plugin = $this->badgeService->getBadgePlugin('streak_badge_plugin')) {
      $daily_streak_badge_plugin->updateUserProgress($user, ['time' => $time]);
    }
    // Timed streak badges are updated on every completed and launched statement
    // they are also fired on opened and closed with session activity.
    if (in_array($verb['display'], ['opened', 'closed']) &&
      $activity->id !== 'ofr-frpal://app.session/session'
    ) {
      return;
    }
    // Update time based badges.
    if ($time_based_badge_plugin = $this->badgeService->getBadgePlugin('time_based_badge_plugin')) {
      $time_based_badge_plugin->updateUserProgress($user,
        [
          'time' => $time,
          'verb' => $verb['display'],
        ]
      );
    }
  }

  /**
   * Get the activity array from the statement object.
   *
   * @param object $statement
   *   The statement object.
   *
   * @return object|null
   *   An array with id, type and name of activity.
   */
  public function getActivityFromStatement($statement) {
    return $statement->object;
  }

  /**
   * Parse the Verb object of the statement.
   *
   * Note: This function is just a convience function to
   * call getFlagOperationFromStatement() as that funciton name
   * doesn't make sense in every situation.
   *
   * @param object $statement
   *   A xApi statement array.
   *
   * @return array
   *   The parsed verb which has two key verb_url and display.
   */
  public function getVerbFromStatement($statement) {
    return $this->getFlagOperationFromStatement($statement);
  }

  /**
   * Retrieve the timestamp from the statement.
   *
   * @param object $statement
   *   A xApi statement array.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   The time the statement was created.
   */
  public function getTimestampFromStatement($statement) {
    if (empty($statement->timestamp)) {
      return NULL;
    }
    return new DrupalDateTime($statement->timestamp);
  }

  /**
   * Parse the verb url in statement and gives back the last part.
   *
   * @param object $statement
   *   A xApi statement array.
   *
   * @return array
   *   The parsed verb which has two key verb_url and flag, display.
   */
  public function getFlagOperationFromStatement($statement) {
    $verb_data = [];
    if (!empty($statement->verb) && !empty($statement->verb->id) && !empty($statement->verb->display)) {
      $verb = $statement->verb;
      $verb_data['verb_url'] = $verb->id;
      $verb_display = reset($verb->display);
      $verb_data['display'] = $verb_display;
    }
    return $verb_data;
  }

}
