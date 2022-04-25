<?php

namespace Drupal\xapi\EventSubscriber;

use Drupal\xapi\XapiStatementHelper;
use Drupal\xapi\XapiActor;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\xapi\XapiActorIFIManager;
use Drupal\xapi\Event\XapiStatementReceived;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Cache the statements from mobile and modify them based on the settings.
 */
class LRSRequestModifier implements EventSubscriberInterface {

  /**
   * Module logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Statement helper service.
   *
   * @var \Drupal\xapi\XapiStatementHelper
   */
  protected $statementHelper;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * An IFI plugin to determine how to represent the actor.
   *
   * @var \Drupal\xapi\XapiActorIFIManager
   */
  protected $ifiManager;

  /**
   * Catch the incoming LRS statements and modify them based on settings.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal config factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Drupal logger service.
   * @param \Drupal\xapi\XapiActorIFIManager $ifi_manager
   *   Actor IFI manager.
   * @param \Drupal\xapi\XapiStatementHelper $statmentHelper
   *   Statement helper service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger,
    XapiActorIFIManager $ifi_manager,
    XapiStatementHelper $statmentHelper) {
    $this->configFactory = $config_factory;
    $this->logger = $logger->get('xapi');
    $this->ifiManager = $ifi_manager;
    $this->statementHelper = $statmentHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[XapiStatementReceived::EVENT_NAME][] = [
      'checkStatement',
      -1000,
    ];
    return $events;
  }

  /**
   * Check that xapi has set the properties in the correct format.
   *
   * @param \Drupal\xapi\Event\XapiStatementReceived $event
   *   The event which contains the xapi statement.
   */
  public function checkStatement(XapiStatementReceived $event) {
    $statement = $event->getStatement();
    $user = $this->statementHelper->getUserFromStatement($statement);

    if ($user) {
      $actor = new XapiActor($this->configFactory, $this->ifiManager);
      $actor->fromUser($user);

      // Ensure the actor is formatted as an object instead of an array.
      $statement->actor = json_decode(json_encode($actor));
    }
  }

}
