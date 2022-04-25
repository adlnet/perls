<?php

namespace Drupal\perls_xapi_reporting\EventSubscriber;

use Drupal\xapi\XapiStatementHelper;
use Drupal\xapi\Event\XapiStatementReceived;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Catch all xapi statement which generated or go trough in CMS.
 */
class XapiEventSubscriber implements EventSubscriberInterface {

  /**
   * Statement helper service.
   *
   * @var \Drupal\xapi\XapiStatementHelper
   */
  protected $statementHelper;

  /**
   * XapiEventSubscriber constructor.
   *
   * @param \Drupal\xapi\XapiStatementHelper $statement_helper
   *   Statement helper service.
   */
  public function __construct(XapiStatementHelper $statement_helper) {
    $this->statementHelper = $statement_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[XapiStatementReceived::EVENT_NAME][] = ['statementReceived', 100];
    return $events;
  }

  /**
   * Add extra data to an existing statement.
   *
   * @param \Drupal\xapi\Event\XapiStatementReceived $event
   *   Xapi event.
   */
  public function statementReceived(XapiStatementReceived $event) {
    $statement = $event->getStatement();

    $user_object = $this->statementHelper->getUserFromStatement($statement);
    if (is_null($user_object) || !$user_object->hasField('field_add_groups')) {
      return;
    }
    $user_groups = $user_object->get('field_add_groups')->referencedEntities();
    $groups = [];
    /** @var \Drupal\group\Entity\Group $group */
    foreach ($user_groups as $group) {
      if (count($groups) === 50) {
        continue;
      }
      $groups[] = [
        'id' => $group->toUrl('canonical', ['absolute' => TRUE])->toString(),
        'name' => [
          $group->language()->getId() => $group->label(),
        ],
      ];
    }
    $extension_name = 'http://xapi.gowithfloat.net/extension/user-groups';

    // Prepare the object.
    if (count($groups) && !property_exists($statement, 'context')) {
      $statement->context = new \stdClass();
    }
    if (count($groups) && !property_exists($statement->context, 'extensions')) {
      $statement->context->extensions = new \stdClass();
    }

    if (count($groups)) {
      $statement->context->extensions->{$extension_name} = $groups;
    }
  }

}
