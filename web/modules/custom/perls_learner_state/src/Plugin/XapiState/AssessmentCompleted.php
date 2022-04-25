<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\Core\Entity\EntityInterface;
use Drupal\perls_learner_state\Plugin\TestStateBase;

/**
 * Define assessment completed state.
 *
 * An assessment can be a test node or a standalone quiz card.
 *
 * @XapiState(
 *  id = "xapi_assessment_completed",
 *  label = @Translation("A user completed an Assessment."),
 *  add_verb = @XapiVerb("completed"),
 *  remove_verb = NULL,
 *  notifyOnXapi = TRUE,
 *  flag = "completed"
 * )
 */
class AssessmentCompleted extends TestStateBase {

  /**
   * {@inheritdoc}
   */
  public function supportsContentType(EntityInterface $entity) {
    if (in_array($entity->bundle(), ['test', 'quiz'])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setTestResult() {
    if (!is_object($this->requestTestData)) {
      return;
    }

    $this->success = $this->requestTestData->success === 'false' ? FALSE : TRUE;
    parent::setTestResult();
  }

}
