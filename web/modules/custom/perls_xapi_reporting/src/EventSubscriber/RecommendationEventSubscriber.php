<?php

namespace Drupal\perls_xapi_reporting\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\core_event_dispatcher\Event\Entity\EntityUpdateEvent;
use Drupal\flag\FlaggingInterface;
use Drupal\perls_xapi_reporting\PerlsXapiActivityType;
use Drupal\perls_xapi_reporting\PerlsXapiVerb;
use Drupal\xapi\XapiActivity;

/**
 * Listens for changes in recommendations.
 *
 * A recomemndation reason and score is not applied when the flagging
 * is intially created; instead we need to wait until the entity
 * is updated.
 */
class RecommendationEventSubscriber extends BaseEntityCrudSubscriber {

  /**
   * {@inheritDoc}
   */
  protected function supportsEntity(EntityInterface $entity): bool {
    return $entity instanceof FlaggingInterface && $entity->getFlagId() === 'recommendation';
  }

  /**
   * {@inheritDoc}
   */
  protected function onEntityUpdated(EntityUpdateEvent $event) {
    $flagging = $event->getEntity();
    $statements = [];

    // Creation of recommendation.
    $statements[] = $this->createStatement($flagging->getFlaggable())
      ->setActorToSystem()
      ->setVerb(PerlsXapiVerb::recommended())
      ->setResultScore($flagging->get('field_recommendation_score')->value)
      ->setResultResponse($flagging->get('field_recommendation_reason')->getString());

    // Receiver of recommendation.
    $activity = XapiActivity::create()
      ->setRelativeId('recommendation/' . $flagging->id())
      ->setName('recommendation for ' . $flagging->getFlaggable()->label())
      ->setType(PerlsXapiActivityType::RECOMMENDATION)
      ->setDescription($flagging->get('field_recommendation_reason')->getString())
      ->setMoreInfo($flagging->getFlaggable()->toUrl('canonical', ['absolute' => TRUE]));
    $statements[] = $this->createStatement()
      ->setActor($flagging->getOwner())
      ->setVerb(PerlsXapiVerb::received())
      ->setActivity($activity)
      ->setResultScore($flagging->get('field_recommendation_score')->value)
      ->addParentContext($flagging->getFlaggable());
    // Recommendations are often calculated during cron runs so we need to
    // pass a user we want to send statements with.
    $this->sendStatements($statements, $flagging->getOwner()->id());
  }

}
