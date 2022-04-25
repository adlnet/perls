<?php

namespace Drupal\prompts;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\prompts\Prompt\PromptManager;
use Drupal\user\Entity\User;

/**
 * Service to manage the prompts.
 */
class Prompt {

  /**
   * Prompts plugin manager.
   *
   * @var \Drupal\prompts\Prompt\PromptManager
   */
  protected $promptManager;

  /**
   * Logged in user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentAccount;

  /**
   * Prompt service which provides prompt's forms.
   *
   * @param \Drupal\prompts\Prompt\PromptManager $prompt_manager
   *   The prompt plugin manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The logged in user.
   */
  public function __construct(PromptManager $prompt_manager, AccountProxyInterface $current_user) {
    $this->promptManager = $prompt_manager;
    $this->currentAccount = $current_user;
  }

  /**
   * Load the prompt question form.
   *
   * @return array
   *   A list of webform submission.
   */
  public function loadPrompts() {
    // This function doesn't make sense to call for Anonymous users, but
    // other services initialized the current_user container too early when the
    // logged in user isn't configured so the current user will be anonymous.
    $current_user = User::load($this->currentAccount->id());
    $prompts = $this->promptManager->getDefinitions();
    $prompt_questions = [];
    foreach ($prompts as $plugin => $prompt) {
      /** @var \Drupal\prompts\Prompt\PromptTypeInterface $prompt_plugin */
      $prompt_plugin = $this->promptManager->createInstance($plugin);
      $questions = $prompt_plugin->getuserQuestions($current_user);
      if (is_array($questions)) {
        $prompt_questions += $questions;
      }
    }
    return $prompt_questions;
  }

  /**
   * Generate the prompt block.
   *
   * @return array
   *   A render array of prompt stack.
   */
  public function generatePromptBlock():array {
    $build = [];
    $forms = $this->loadPrompts();
    if (!empty($forms)) {
      $build = [
        '#theme' => 'prompt_block',
        '#prompts' => [],
      ];

      /** @var \Drupal\webform\Entity\WebformSubmission $prompt_question */
      $count = 0;
      $questions = [];
      foreach ($forms as $prompt_question) {
        $classes = ['prompt-wrapper'];
        if (!$count) {
          $classes[] = 'top';
        }
        $count++;
        $questions[] = [
          '#type' => 'container',
          '#attributes' => [
            'submission-uuid' => $prompt_question->uuid(),
            'webform-id' => $prompt_question->getWebform()->id(),
            'class' => $classes,
          ],
          'webform' => [
            '#type' => 'webform',
            '#information' => FALSE,
            '#sid' => $prompt_question->id(),
            '#webform' => $prompt_question->getWebform()->id(),
            '#cache' => ['user'],
          ],
        ];
      }
      $build['#prompts'] = $questions;
      if (perls_xapi_reporting_user_needs_report()) {
        $build['#attached']['library'] = ['prompts/prompts.xapi_reporting'];
      }
    }
    return $build;
  }

}
