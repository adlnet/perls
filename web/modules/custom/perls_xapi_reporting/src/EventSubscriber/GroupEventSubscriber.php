<?php

namespace Drupal\perls_xapi_reporting\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\core_event_dispatcher\Event\Entity\EntityDeleteEvent;
use Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\perls_xapi_reporting\PerlsXapiVerb;

/**
 * Listens for changes in group membership.
 */
class GroupEventSubscriber extends BaseEntityCrudSubscriber {

  /**
   * {@inheritDoc}
   */
  protected function supportsEntity(EntityInterface $entity): bool {
    return $entity instanceof GroupContentInterface && $entity->getGroupContentType()->getContentPluginId() === 'group_membership';
  }

  /**
   * {@inheritDoc}
   */
  protected function onEntityInserted(EntityInsertEvent $event) {
    $membership = $event->getEntity();
    $statement = $this->createStatement($membership->getGroup())
      ->setActor($membership->getEntity())
      ->setVerb(PerlsXapiVerb::join());

    $this->sendStatement($statement, $membership->getEntity()->id());
  }

  /**
   * {@inheritDoc}
   */
  protected function onEntityDeleted(EntityDeleteEvent $event) {
    $membership = $event->getEntity();

    // If the associated user account has been deleted,
    // then we don't have enough information to report this statement.
    if (!$membership->getEntity()) {
      return;
    }

    $statement = $this->createStatement($membership->getGroup())
      ->setActor($membership->getEntity())
      ->setVerb(PerlsXapiVerb::leave());

    $this->sendStatement($statement, $membership->getEntity()->id());
  }

}
