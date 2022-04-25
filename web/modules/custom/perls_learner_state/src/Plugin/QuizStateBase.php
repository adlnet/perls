<?php

namespace Drupal\perls_learner_state\Plugin;

use Drupal\Core\Entity\EntityInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\perls_learner_state\PerlsLearnerStatementFlag;
use Drupal\xapi\XapiActivity;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\perls_xapi_reporting\PerlsXapiActivityType;
use Drupal\xapi\LRSRequestGenerator;
use Drupal\xapi\XapiActorIFIManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\xapi\XapiActivityProviderInterface;
use Drupal\perls_learner_state\UserQuizQuestion;

/**
 * Base class for quiz related states.
 */
class QuizStateBase extends XapiStateBase {

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
  protected $requestQuizData;

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
   *   A helper service to manage sync between statement and flags.
   * @param \Drupal\xapi\LRSRequestGenerator $request_generator
   *   The request generator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
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
    UserQuizQuestion $userQuizQuestion) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $current_user, $config_factory, $module_handler, $ifi_manager, $activity_provider, $flag_statement_helper, $request_generator);
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('perls_learner_state.quiz_per_user')
    );
  }

  /**
   * The quiz related statement needs extra data.
   *
   * @param object $extra_data
   *   The js pass some extra data from user answers.
   */
  public function processExtraData($extra_data) {
    $this->requestQuizData = $extra_data;

    if (isset($extra_data->parent) && is_numeric($extra_data->parent)) {
      $node = $this->entityTypeManager->getStorage('node')->load($extra_data->parent);
      $parent_activity = XapiActivity::createFromEntity($node);

      if ($node->bundle() === 'quiz') {
        $parent_activity_id = $parent_activity->getId();
        $parent_activity
          ->setId($parent_activity_id . '#assessment')
          ->setType(PerlsXapiActivityType::ASSESSMENT);
      }

      $this->statement->addParentContext($parent_activity);
    }
  }

  /**
   * Add the registration id to context.
   */
  protected function addRegistrationToContext() {
    if (isset($this->requestQuizData) && isset($this->requestQuizData->registration)) {
      $this->statement->setRegistration($this->requestQuizData->registration);
    }
  }

  /**
   * Attempt to find an assessment attempt with a given uuid.
   */
  protected function getTestAttempt($registration_id) {
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
   * Create an assessment attempt with a given registration uuid.
   */
  protected function createTestAttempt($registration_id, EntityInterface $entity) {
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

}
