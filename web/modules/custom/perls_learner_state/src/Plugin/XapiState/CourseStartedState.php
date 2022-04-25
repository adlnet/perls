<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\Core\Entity\EntityInterface;
use Drupal\perls_learner_state\Plugin\XapiStateBase;
use Drupal\user\UserInterface;

/**
 * Define started course state.
 *
 * @XapiState(
 *  id = "xapi_started_course_state",
 *  label = @Translation("Xapi course started state"),
 *  add_verb = @XapiVerb("launched"),
 *  remove_verb = NULL,
 *  notifyOnXapi = TRUE,
 *  flag = "started_course",
 * )
 */
class CourseStartedState extends XapiStateBase {

  /**
   * {@inheritdoc}
   */
  public function supportsContentType(EntityInterface $entity) {
    return $entity->bundle() === 'course';
  }

  /**
   * {@inheritdoc}
   */
  public function flagSync(EntityInterface $entity, UserInterface $user, array $extra_data, $statement = NULL) {
    if (!$this->supportsContentType($entity)) {
      return NULL;
    }
    return $this->flagStatementHelper->createNewFlagOnce($entity, $this->getFlagName(), $user, $extra_data);
  }

}
