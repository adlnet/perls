<?php

namespace Drupal\perls_xapi_reporting\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\notifications\Event\PushNotificationToUser;
use Drupal\perls_xapi_reporting\PerlsXapiVerb;
use Drupal\xapi\LRSRequestGenerator;
use Drupal\xapi_reporting\XapiStatementCreator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber which listening for push notification event.
 */
class PushNotificationSubscriber implements EventSubscriberInterface {

  /**
   * Statement Generator.
   *
   * @var \Drupal\xapi\LRSRequestGenerator
   */
  protected $statementGenerator;


  /**
   * Statement Creator.
   *
   * @var \Drupal\xapi_reporting\XapiStatementCreator
   */
  protected $statementCreator;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Current request route.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRoute;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new PushNotificationSubscriber object.
   */
  public function __construct(
    LRSRequestGenerator $statementGenerator,
    XapiStatementCreator $statementCreator,
    AccountProxyInterface $current_user,
    CurrentRouteMatch $current_route,
    LoggerChannelFactoryInterface $logger,
    ConfigFactoryInterface $configFactory) {
    $this->statementGenerator = $statementGenerator;
    $this->statementCreator = $statementCreator;
    $this->currentUser = $current_user;
    $this->currentRoute = $current_route;
    $this->logger = $logger;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[PushNotificationToUser::PUSH_NOTIFICATION_TO_USER] = ['pushNotification'];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function pushNotification(PushNotificationToUser $event) {

    /** @var \Drupal\xapi\XapiStatement $statement */
    $statement = $this->statementCreator->getEntityTemplateStatement($event->getEntity());
    $entity = $event->getEntity();
    $message = $entity->getMessageData();
    $uid = $this->currentUser->id();

    // If the action is achievementEarned.
    if (!empty($message['action']) && ($message['action'] == 'achievementEarned')) {
      // User can be anon or authenticated.
      $statement->setActorToSystem();
    }
    else {
      $statement->setActor($this->currentUser);
    }

    // If the user is anonymous.
    if ($this->currentUser->isAnonymous()) {
      $uid = 1;
    }

    $statement->setVerb(PerlsXapiVerb::send())->setObject($event->getEntity());

    $recipientUrls = [];
    /** @var \Drupal\user\Entity\User $recipient */
    foreach ($entity->get('recipients')->referencedEntities() as $recipient) {
      $recipientUrls[] = $recipient->toUrl()->setAbsolute()->toString();
    }

    // Prepare the object.
    if (count($recipientUrls)) {
      $statement->getObject()->addExtensions([
        'http://id.tincanapi.com/extension/target' => $recipientUrls,
      ]);
    }

    $this->statementGenerator->sendStatements([$statement], $uid);
  }

}
