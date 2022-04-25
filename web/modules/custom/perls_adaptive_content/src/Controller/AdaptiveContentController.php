<?php

namespace Drupal\perls_adaptive_content\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A controller class to update adaptive content.
 *
 * @package Drupal\perls_adaptive_content\Controller
 */
class AdaptiveContentController extends ControllerBase {

  /**
   * Node storage service.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * A logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $moduleLogger;

  /**
   * Adaptive Content controller.
   *
   * @param \Drupal\node\NodeStorageInterface $node_storage
   *   Node storage service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The drupal logger service.
   */
  public function __construct(NodeStorageInterface $node_storage, LoggerChannelFactoryInterface $logger) {
    $this->nodeStorage = $node_storage;
    $this->moduleLogger = $logger->get('perls_xapi_reporting');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get('logger.factory')
    );
  }

  /**
   * Create an ajax response to update course completions after adaptive tests.
   *
   * @param \Drupal\Core\Entity\EntityInterface $course
   *   The course node that needs to be refreshed.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A symfony request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty Response object.
   */
  public function refreshCourse(EntityInterface $course, Request $request) {
    $response = new AjaxResponse();
    if ($course->bundle() !== 'course') {
      return $response;
    }
    try {
      $nodes = $course->field_learning_content->referencedEntities();
      foreach ($nodes as $node) {
        if ($node->bundle() === 'test') {
          continue;
        }
        $content = \Drupal::entityTypeManager()->getViewBuilder('node')->view($node, 'card');
        $response->addCommand(new ReplaceCommand('article.c-node--card[node-id=' . $node->id() . ']', $content));

      }

    }
    catch (\Exception $exception) {
      $this->moduleLogger->error('Adaptive Learning refresh failed: %id', ['%id' => $course]);
    }
    $progress = \Drupal::service('perls_learner_state.info')->getCourseProgress($course);
    $response->addCommand(new ReplaceCommand('article.c-node--full--course .o-progress strong', '<strong>' . $progress . ' / ' . count($nodes) . '</strong>'));
    return $response;
  }

}
