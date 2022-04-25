<?php

namespace Drupal\prompts\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\prompts\Prompt;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contains route responses for user prompts.
 *
 * @package Drupal\prompts\Controller
 */
class MyPromptsPage extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The prompt helper service.
   *
   * @var \Drupal\prompts\Prompt
   */
  protected $promptService;

  /**
   * MyPromptsPage constructor.
   *
   * @param \Drupal\prompts\Prompt $prompt_service
   *   Prompt helper service.
   */
  public function __construct(Prompt $prompt_service) {
    $this->promptService = $prompt_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('prompts.prompt')
    );
  }

  /**
   * Show the user actual prompts question in a modal.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response which open the prompt in a modal.
   */
  public function inModal():AjaxResponse {
    $content = $this->promptService->generatePromptBlock();
    $content['#in_modal'] = TRUE;
    $options = [
      'dialogClass' => 'prompt-questions-modal',
    ];
    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand("", $content, $options));

    return $response;
  }

}
