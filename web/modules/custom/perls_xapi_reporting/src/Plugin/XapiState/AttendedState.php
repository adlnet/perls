<?php

namespace Drupal\perls_xapi_reporting\Plugin\XapiState;

use Drupal\Core\Entity\EntityInterface;
use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define launched state.
 *
 * @XapiState(
 *  id = "xapi_attended_state",
 *  label = @Translation("Xapi attended state"),
 *  add_verb = @XapiVerb("attended"),
 *  remove_verb = NULL,
 *  notifyOnXapi = TRUE,
 *  flag = "attended"
 * )
 */
class AttendedState extends XapiStateBase {

  /**
   * {@inheritdoc}
   */
  public function supportsContentType(EntityInterface $entity) {
    return ($entity->getEntityTypeId() === 'node' && $entity->bundle() === 'event');
  }

}
