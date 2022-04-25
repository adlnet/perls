<?php

namespace Drupal\prompts\Controller;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\prompts\Prompt;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * A controller which provides the promp forms.
 */
class PromptsController extends ControllerBase {

  /**
   * A serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * The prompt generator service.
   *
   * @var \Drupal\prompts\Prompt
   */
  protected $prompt;

  /**
   * PromptsController constructor.
   *
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer service.
   * @param \Drupal\prompts\Prompt $prompt
   *   The prompt generator service.
   */
  public function __construct(SerializerInterface $serializer, Prompt $prompt) {
    $this->serializer = $serializer;
    $this->prompt = $prompt;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('serializer'),
      $container->get('prompts.prompt')
    );
  }

  /**
   * Response to /api/prompts endpoint.
   */
  public function getPromptForm() {
    $submissions = $this->prompt->loadPrompts();
    if (!empty($submissions)) {
      $json = $this->serializer->serialize(array_values($submissions), 'json');
      $response = new CacheableResponse($json, Response::HTTP_OK, ['Content-Type' => 'application/json']);
      foreach ($submissions as $submission) {
        $response->addCacheableDependency($submission);
      }
      $response->getCacheableMetadata()->addCacheTags(['webform_submission_list'])->addCacheContexts(['user']);
      return $response;
    }
    else {
      return new JsonResponse([]);
    }
  }

}
