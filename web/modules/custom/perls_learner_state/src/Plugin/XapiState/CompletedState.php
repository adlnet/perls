<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\user\UserInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use Drupal\perls_learner_state\Plugin\XapiStateBase;

/**
 * Define completed state.
 *
 * @XapiState(
 *  id = "xapi_completed_state",
 *  label = @Translation("Xapi completed state"),
 *  add_verb = @XapiVerb("completed"),
 *  remove_verb = NULL,
 *  notifyOnXapi = TRUE,
 *  flag = "completed",
 * )
 */
class CompletedState extends XapiStateBase {

  /**
   * {@inheritdoc}
   */
  public function supportsContentType(EntityInterface $entity) {
    // Assessment-related content types are completed via
    // the AssessmentCompleted plugin.
    if (!($entity instanceof NodeInterface) ||
      in_array($entity->bundle(), ['test', 'quiz'])) {
      return FALSE;
    }
    else {
      return TRUE;
    }
  }

  /**
   * {@inheritDoc}
   */
  protected function prepareStatement(int $timestamp = NULL, UserInterface $user = NULL) {
    parent::prepareStatement($timestamp, $user);
    $this->statement->setResultCompletion();
  }

}
