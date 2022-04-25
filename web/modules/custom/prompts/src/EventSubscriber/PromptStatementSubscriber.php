<?php

namespace Drupal\prompts\EventSubscriber;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\xapi\XapiVerb;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\prompts\Prompt\PromptManager;
use Drupal\xapi\Event\XapiStatementReceived;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber to react to prompt statements.
 */
class PromptStatementSubscriber implements EventSubscriberInterface {

  /**
   * The current drupal request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $currentRequest;

  /**
   * Drupal entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Prompt manager.
   *
   * @var \Drupal\prompts\Prompt\PromptManager
   */
  protected $promptManager;

  /**
   * Event subscriber which listen for xapi statements.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   A drupal request.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   Drupal entity type manager interface.
   * @param \Drupal\prompts\Prompt\PromptManager $prompt_manager
   *   The prompt type manager.
   */
  public function __construct(RequestStack $request, EntityTypeManager $entity_type_manager, PromptManager $prompt_manager) {
    $this->currentRequest = $request->getCurrentRequest();
    $this->entityTypeManager = $entity_type_manager;
    $this->promptManager = $prompt_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[XapiStatementReceived::EVENT_NAME][] = ['syncPrompts', -100];
    return $events;
  }

  /**
   * Sync the statements between app and cms.
   *
   * @param \Drupal\xapi\Event\XapiStatementReceived $event
   *   An xapi statement event from app.
   */
  public function syncPrompts(XapiStatementReceived $event) {
    $statement = $event->getStatement();
    if ($this->isPromptResponse($statement)) {
      $submission = $this->getWebformSubmission($statement);
      /** @var \Drupal\webform\Entity\WebformSubmission $submission */
      if ($submission && !$submission->isLocked()) {
        $submission->set('completed', strtotime($statement->timestamp));
        $submission->set('in_draft', 0);
        $response = $this->getStatementResult($statement);
        if ($response) {
          $prompt_plugins = $this->promptManager->getDefinitions();
          /** @var \Drupal\prompts\Prompt\PromptTypeInterface $plugin */
          foreach ($prompt_plugins as $plugin_id => $plugin) {
            if ($plugin['webform'] === $submission->getWebform()->id()) {
              $submission->setElementData($plugin['questionField'], $response);
              continue;
            }
          }
        }
        $submission->save();
      }
    }
  }

  /**
   * Checks if statement is a prompt response.
   *
   * A statement is a prompt response if the object ID follows
   * the pattern of {host}/submission/{submission_id} AND
   * the verb is the ADL "responded" verb.
   *
   * @param object $statement
   *   The statement object.
   *
   * @return bool
   *   TRUE if the statement is a prompt response.
   */
  protected function isPromptResponse($statement): bool {
    // Verify the statement is responding to something.
    if ($statement->verb->id !== XapiVerb::responded()->getId()) {
      return FALSE;
    }

    // Verify the statement object is an activity.
    if (isset($statement->object->objectType) && $statement->object->objectType !== 'Activity') {
      return FALSE;
    }

    // Parse the activity ID and verify it follows the pattern.
    $path = parse_url($statement->object->id, PHP_URL_PATH);
    return substr($path, 0, 12) === "/submission/";
  }

  /**
   * Load the webform submission id.
   *
   * @param object $statement
   *   An xapi statement which contains a submission.
   *
   * @return \Drupal\webform\WebformSubmissionInterface|null
   *   A webform submission object.
   */
  protected function getWebformSubmission($statement): ?WebformSubmissionInterface {
    list(, $type, $id) = explode('/', parse_url($statement->object->id, PHP_URL_PATH));

    if (!$id) {
      return NULL;
    }

    $webformStorage = $this->entityTypeManager->getStorage('webform_submission');

    // ID is expected to be either a UUID or an integer.
    if (Uuid::isValid($id)) {
      $submissions = $webformStorage->loadByProperties(['uuid' => $id]);
      if (empty($submissions)) {
        return NULL;
      }

      return reset($submissions);
    }
    elseif (is_int($id)) {
      return $webformStorage->load($id);
    }

    return NULL;
  }

  /**
   * Gets the result of a statement.
   *
   * @param object $statement
   *   An xapi statement which contains a webform submission.
   *
   * @return mixed
   *   The result if it exists otherwise NULL.
   */
  protected function getStatementResult($statement) {
    if (isset($statement->result)) {
      return $statement->result->response;
    }

    return NULL;
  }

}
