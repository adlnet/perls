<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\Core\Entity\EntityInterface;
use Drupal\perls_learner_state\Plugin\TestStateBase;
use Drupal\user\UserInterface;

/**
 * Define passed test state.
 *
 * @XapiState(
 *  id = "xapi_assessment_passed",
 *  label = @Translation("A user passed on a test."),
 *  add_verb = @XapiVerb("passed"),
 *  remove_verb = NULL,
 *  notifyOnXapi = TRUE,
 *  flag = "test_results"
 * )
 */
class AssessmentPassed extends TestStateBase {

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
    $this->success = TRUE;
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
   * The feedback string used when you pass a test.
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
