<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\Core\Entity\EntityInterface;
use Drupal\perls_learner_state\Plugin\TestStateBase;
use Drupal\user\UserInterface;

/**
 * Define failed assessment state.
 *
 * An assessment can be a test node or a standalone quiz card.
 *
 * @XapiState(
 *  id = "xapi_assessment_failed",
 *  label = @Translation("A user failed on a assessment."),
 *  add_verb = @XapiVerb("failed"),
 *  remove_verb = NULL,
 *  notifyOnXapi = TRUE,
 *  flag = "test_results"
 * )
 */
class AssessmentFailed extends TestStateBase {

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
    $this->success = FALSE;
    parent::setTestResult();
  }

  /**
   * {@inheritdoc}
   */
  public function flagSync(EntityInterface $entity, UserInterface $user, array $extra_data, $statement = NULL) {
    // Only need to update flag for tests.
    if ($entity->bundle() !== 'test') {
      return;
    }
    // Both passed and failed states do the same thing so method is in base.
    $this->syncTestResult($entity, $user, $extra_data, $statement);
  }

  /**
   * The feedback string for when you fail a test.
   */
  public function getFeedbackString($result, $correct_count, $question_count) {
    return $this->t(
      '<h2>@result %</h2><div>You answered <span class="correct">@correct</span> out of <span class="total">@total</span> correct.</div>',
      [
        '@result' => intval($result * 100),
        '@correct' => $correct_count,
        '@total' => $question_count,
      ]
    );
  }

}
