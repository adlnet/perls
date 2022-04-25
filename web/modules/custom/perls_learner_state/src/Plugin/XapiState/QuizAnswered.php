<?php

namespace Drupal\perls_learner_state\Plugin\XapiState;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\perls_learner_state\PerlsLearnerStatementFlag;
use Drupal\perls_learner_state\Plugin\QuizStateBase;
use Drupal\perls_learner_state\UserQuizQuestion;
use Drupal\user\UserInterface;
use Drupal\xapi\LRSRequestGenerator;
use Drupal\xapi\XapiActivityProviderInterface;
use Drupal\xapi\XapiActorIFIManager;
use Drupal\xapi\XapiStatementHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define answered quiz question state.
 *
 * @XapiState(
 *  id = "xapi_quiz_question_answered",
 *  label = @Translation("Xapi quiz question answered by user"),
 *  add_verb = @XapiVerb("answered"),
 *  remove_verb = NULL,
 *  notifyOnXapi = TRUE,
 *  flag = ""
 * )
 */
class QuizAnswered extends QuizStateBase {

  /**
   * Helper service to manage xapi statements.
   *
   * @var \Drupal\xapi\XapiStatementHelper
   */
  protected XapiStatementHelper $xapiStatementHelper;

  /**
   * Constructs a test state plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\xapi\XapiActorIFIManager $ifi_manager
   *   The actor IFI manager.
   * @param \Drupal\xapi\XapiActivityProviderInterface $activity_provider
   *   The actor provider.
   * @param \Drupal\perls_learner_state\PerlsLearnerStatementFlag $flag_statement_helper
   *   A helper service to manage sync between statement and flags.
   * @param \Drupal\xapi\LRSRequestGenerator $request_generator
   *   The request generator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\perls_learner_state\UserQuizQuestion $userQuizQuestion
   *   User Quiz Question service.
   * @param \Drupal\xapi\XapiStatementHelper $xapi_statement_helper
   *   Statement helper to manage xapi statements.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AccountInterface $current_user,
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
    XapiActorIFIManager $ifi_manager,
    XapiActivityProviderInterface $activity_provider,
    PerlsLearnerStatementFlag $flag_statement_helper,
    LRSRequestGenerator $request_generator,
    EntityTypeManagerInterface $entity_type_manager,
    UserQuizQuestion $userQuizQuestion,
    XapiStatementHelper $xapi_statement_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $current_user, $config_factory, $module_handler, $ifi_manager, $activity_provider, $flag_statement_helper, $request_generator, $entity_type_manager, $userQuizQuestion);
    $this->xapiStatementHelper = $xapi_statement_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('plugin.manager.xapi_actor_ifi'),
      $container->get('xapi.activity_provider'),
      $container->get('perls_learner_state.flagging_helper'),
      $container->get('lrs.request_generator'),
      $container->get('entity_type.manager'),
      $container->get('perls_learner_state.quiz_per_user'),
      $container->get('xapi.xapi_statement_helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareStatement(int $timestamp = NULL, UserInterface $user = NULL) {
    parent::prepareStatement($timestamp, $user);
    $this->addRegistrationToContext();
    $this->setQuizResult();
  }

  /**
   * Sets the quiz result.
   */
  protected function setQuizResult() {
    $success = $this->requestQuizData->success === 'false' ? FALSE : TRUE;
    $this->statement
      ->setResultResponse($this->requestQuizData->answer)
      ->setResultCompletion()
      ->setResultSuccess($success)
      ->setResultDuration($this->requestQuizData->duration / 1000)
      ->setResultScore($success ? 1 : 0);
  }

  /**
   * {@inheritdoc}
   */
  public function flagSync(EntityInterface $entity, UserInterface $user, array $extra_data, $statement = NULL) {
    // We only are interested in test answers so check for quiz here.
    // The object will always be a quiz cards so need to check parent.
    if (!(isset($statement) && isset($statement->context) && isset($statement->context->contextActivities) && isset($statement->context->contextActivities->parent))) {
      return;
    }
    $parent = $this->xapiStatementHelper->getParentFromStatement($statement);
    if (!$parent) {
      return;
    }
    if ($parent->bundle() !== 'test') {
      return;
    }
    // Answering a question doesn't create a new flagging but does
    // update an attempt. We use registration id to locate the attempt
    // and update it with the data from the xapi statement.
    if (isset($statement) && isset($statement->context) && isset($statement->context->registration)) {
      $registration_id = $statement->context->registration;
      // Need to get the flagging to add this.
      $flagging = $this->flagStatementHelper->createNewFlag($parent, 'test_results', $user, []);
      /** @var \Drupal\paragraphs\Entity\Paragraph $attempt */
      $attempt = $this->getTestAttempt($registration_id);

      if (!$attempt) {
        $attempt = $this->createTestAttempt($registration_id, $parent);
        $flagging->field_test_attempts[] = $attempt;
      }
      // Save the answer the user just added into retrieved attempt.
      if (isset($statement->result) && isset($statement->result->success) && isset($statement->result->response)) {
        $correct = $attempt->field_correctly_answered_count->value;

        // We need to double check for duplicate statements here.
        $existing_answer = [];
        if (!$attempt->isNew()) {
          $existing_answer = $this->entityTypeManager->getStorage('paragraph')->loadByProperties([
            'type' => 'test_question_answer',
            'parent_id' => $attempt->id(),
            'parent_type' => 'paragraph',
            'field_quiz_card' => $entity->id(),
          ]);
        }

        $user_response = substr($statement->result->response, 0, 255);

        if (!empty($existing_answer)) {
          $answer = reset($existing_answer);
          // If this answer existed remove it from correct
          // count so that we don't count it twice.
          if ($answer->field_answer_correct->getValue()) {
            $correct -= 1;
          }
          $answer->field_answer_correct = $statement->result->success;
          $answer->field_user_answer = $user_response;
        }
        else {
          $answer = Paragraph::create([
            'type' => 'test_question_answer',
            'field_answer_correct' => $statement->result->success,
            'field_user_answer' => $user_response,
            'field_quiz_card' => $entity,
          ]);
          if (isset($extra_data) && isset($extra_data['created'])) {
            $answer->created = $extra_data['created'];
          }

          // Calculate the total quiz questions for the user.
          $quiz = \Drupal::service('perls_learner_state.quiz_per_user')->getQuiz($parent);

          $attempt->field_attempted_answers[] = $answer;
          $attempt->field_test_question_count = $quiz;
        }
        $answer->save();

        $attempt->field_correctly_answered_count = ($statement->result->success) ? $correct + 1 : $correct;
        if ((int) $attempt->field_correctly_answered_count->value > 0 || (int) $attempt->field_test_question_count->value > 0) {
          $attempt->field_test_result = (int) $attempt->field_correctly_answered_count->value / (int) $attempt->field_test_question_count->value;
        }
        else {
          $attempt->field_test_result = 0;
        }
        $attempt->save();
        $flagging->save();
      }
    }
  }

}
