<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\Core\Entity\EntityInterface;
use Drupal\perls_learner_state\Plugin\TestStateBase;
use Drupal\user\UserInterface;

/**
 * Define test attempted state.
 *
 * @XapiState(
 *  id = "xapi_test_attempted_state",
 *  label = @Translation("Xapi test attempted state"),
 *  add_verb = @XapiVerb("attempted"),
 *  remove_verb = NULL,
 *  notifyOnXapi = TRUE,
 *  flag = "test_results"
 * )
 */
class TestAttemptState extends TestStateBase {

  /**
   * {@inheritdoc}
   */
  public function setTestResult() {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   *
   * This flag sync creates a test_result flag and adds
   * an attempt with the provided registration id.
   */
  public function flagSync(EntityInterface $entity, UserInterface $user, array $extra_data, $statement = NULL) {
    if ($entity->bundle() !== 'test') {
      return;
    }

    if ($flagging = parent::flagSync($entity, $user, $extra_data, $statement)) {
      if (isset($statement) && isset($statement->context) && isset($statement->context->registration)) {
        $registration_id = $statement->context->registration;
        $attempt = $this->getTestAttempt($registration_id);
        if (!$attempt) {
          $attempt = $this->createTestAttempt($registration_id, $entity);
        }
        // Sync the attempt times between statement and $flagging.
        if (isset($extra_data) && isset($extra_data['created'])) {
          $attempt->created = $extra_data['created'];
        }
        // If this hasn't been added to flagging add it now.
        if (!$attempt->parent_id->value) {
          $flagging->field_test_attempts[] = $attempt;
        }
        $attempt->save();
        $flagging->save();
      }
    }
  }

}
