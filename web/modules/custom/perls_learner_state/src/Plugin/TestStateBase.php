<?php

namespace Drupal\perls_learner_state\Plugin;

use Drupal\perls_learner_state\PerlsLearnerStatementFlag;
use Drupal\user\UserInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\perls_xapi_reporting\PerlsXapiActivityType;
use Drupal\xapi\LRSRequestGenerator;
use Drupal\xapi\XapiActorIFIManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\xapi\XapiActivityProviderInterface;
use Drupal\perls_adaptive_content\AdaptiveContentServiceInterface;
use Drupal\perls_learner_state\UserQuizQuestion;

/**
 * Base class for test related states.
 */
class TestStateBase extends XapiStateBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Contains extra data for this statement which are needed in statement.
   *
   * These data are coming from js side, like duration, option selected.
   *
   * @var object
   */
  protected $requestTestData;

  /**
   * Whether the test attempt was successful.
   *
   * @var bool
   */
  protected $success;

  /**
   * The Adaptive learning service.
   *
   * @var \Drupal\perls_adaptive_content\AdaptiveContentServiceInterface
   */
  protected $adaptiveContentService;

  /**
   * The UserQuizQuestion service.
   *
   * @var \Drupal\perls_learner_state\UserQuizQuestion
   */
  protected $userQuizQuestion;

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
   *   A helper service to manage sync the statement and flags.
   * @param \Drupal\xapi\LRSRequestGenerator $request_generator
   *   The request generator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\perls_adaptive_content\AdaptiveContentServiceInterface $adaptive_content_service
   *   Adaptive content service.
   * @param \Drupal\perls_learner_state\UserQuizQuestion $userQuizQuestion
   *   User Quiz Question service.
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
    AdaptiveContentServiceInterface $adaptive_content_service,
    UserQuizQuestion $userQuizQuestion) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $current_user, $config_factory, $module_handler, $ifi_manager, $activity_provider, $flag_statement_helper, $request_generator);
    $this->entityTypeManager = $entity_type_manager;
    $this->adaptiveContentService = $adaptive_content_service;
    $this->userQuizQuestion = $userQuizQuestion;
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
      $container->get('perls_adaptive_content.adaptive_content_service'),
      $container->get('perls_learner_state.quiz_per_user')
    );
  }

  /**
   * The test related statement needs extra data.
   *
   * @param object $extra_data
   *   The js pass some extra data from user answers.
   */
  public function processExtraData($extra_data) {
    $this->requestTestData = $extra_data;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareStatement(int $timestamp = NULL, UserInterface $user = NULL) {
    global $base_url;
    parent::prepareStatement($timestamp, $user);
    $this->addRegistrationToContext();
    $this->setTestResult();

    // Add #Assessment to standalone quiz card ids.
    /** @var \Drupal\node\NodeInterface $entity */
    $entity = $this->getStatementContent();
    if ($entity && $entity->bundle() === 'quiz') {
      $id = $base_url . $entity->toUrl()->toString();
      $this->statement->getObject()
        ->setId($id . '#assessment')
        ->setType(PerlsXapiActivityType::ASSESSMENT);
    }

    // Load the first question.
    if ($entity->hasField('field_quiz')) {
      $questions = $entity->get('field_quiz')->referencedEntities();
      if (!empty($questions)) {
        $this->statement->addGroupingContext($questions[0]);
      }
    }
  }

  /**
   * A method to add the registration id to context of an xapi statement.
   */
  public function addRegistrationToContext() {
    if (isset($this->requestTestData) && isset($this->requestTestData->registration)) {
      $this->statement->setRegistration($this->requestTestData->registration);
    }
  }

  /**
   * A list of node types this plugin can flag.
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
   * Check to see if a test attempt exists for a given registration id.
   */
  public function getTestAttempt($registration_id) {
    // Check if attempt already exists:
    $attempts = $this->entityTypeManager->getStorage('paragraph')->loadByProperties([
      'type' => 'test_attempt',
      'field_registration_id' => $registration_id,
    ]);
    if (empty($attempts)) {
      return NULL;
    }
    else {
      return reset($attempts);
    }
  }

  /**
   * Create a new test attempt paragraph.
   */
  public function createTestAttempt($registration_id, EntityInterface $entity) {
    // Get the published quiz count.
    $quiz = $this->userQuizQuestion->getQuiz($entity);

    $attempt = Paragraph::create([
      'type' => 'test_attempt',
      'field_registration_id' => $registration_id,
      'field_test_complete' => FALSE,
      'field_test_passed' => FALSE,
      'field_test_feedback' => '',
      'field_test_question_count' => $quiz,
      'field_test_result' => 0,
      'field_correctly_answered_count' => 0,
    ]);
    return $attempt;
  }

  /**
   * Set the result property of a statement.
   */
  public function setTestResult() {
    $this->statement
      ->setResultCompletion()
      ->setResultSuccess($this->success);

    if ($this->requestTestData->duration) {
      $this->statement->setResultDuration($this->requestTestData->duration / 1000);
    }

    $entity = $this->getStatementContent();

    if ($entity->bundle() === 'quiz') {
      $this->statement->setResultScore($this->success ? 1 : 0);
    }
    else {
      // Get the published quiz count.
      $quiz = $this->userQuizQuestion->getQuiz($entity);
      $this->statement->setResultScore($this->requestTestData->score, $quiz);
    }
  }

  /**
   * Mark a test attemp as complete and passed or failed.
   */
  public function syncTestResult(EntityInterface $entity, UserInterface $user, array $extra_data, $statement = NULL) {
    // We can only sync results if we have the statement and the
    // statement has a result section.
    if (
      !(isset($statement) &&
      isset($statement->result) &&
      isset($statement->result->score)
      )
      ) {
      return;
    }
    // Get the pass mark from the entity.
    $pass_mark = 1;
    if ($entity->hasField('field_pass_mark')) {
      $pass_mark = $entity->field_pass_mark->value / 100;
    }
    // We need to get the correct attempt based on registration id.
    // It should be already created but since xapi can arrive out of order
    // we create it if it is missing.
    if ($flagging = parent::flagSync($entity, $user, [], $statement)) {
      if (isset($statement) && isset($statement->context) && isset($statement->context->registration)) {
        $registration_id = $statement->context->registration;
        $attempt = $this->getTestAttempt($registration_id);
        if (!$attempt) {
          $attempt = $this->createTestAttempt($registration_id, $entity);
          // Sync the attempt times between statement and $flagging.
          if (isset($extra_data) && isset($extra_data['created'])) {
            $attempt->created = $extra_data['created'];
          }
        }
        // We now have the correct attempt so we can add our new information.
        $attempt->field_test_complete = TRUE;
        $attempt->field_test_passed = ($statement->result->score->scaled >= $pass_mark) ? TRUE : FALSE;
        $attempt->field_test_result = $statement->result->score->scaled;
        $attempt->field_correctly_answered_count = $statement->result->score->raw;

        if ($this->adaptiveContentService->isTestAdaptive($entity)) {
          $attempt->field_test_feedback = [
            'value' => $this->getAdaptiveString($entity, $statement->result->score->scaled, $statement->result->score->raw, $statement->result->score->max),
            'format' => 'card_styling',
          ];
        }
        else {
          $attempt->field_test_feedback = [
            'value' => $this->getFeedbackString($statement->result->score->scaled, $statement->result->score->raw, $statement->result->score->max),
            'format' => 'card_styling',
          ];
        }

        $attempt->save();

        // Set the default feedback.
        // If this hasn't been added to flagging add it now.
        if (!$attempt->parent_id->value) {
          $flagging->field_test_attempts[] = $attempt;
        }
        $flagging->save();
      }
    }
  }

  /**
   * This is the default feedback string for any test attempt that is completed.
   *
   * This is overridden once the test is passed or failed.
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

  /**
   * A special feedback message for adaptive content.
   */
  public function getAdaptiveString(EntityInterface $entity, $result, $correct_count, $question_count) {
    return $this->adaptiveContentService->getTestFeedback($entity, $result, $correct_count, $question_count);
  }

}
