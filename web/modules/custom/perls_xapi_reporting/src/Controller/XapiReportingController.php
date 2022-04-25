<?php

namespace Drupal\perls_xapi_reporting\Controller;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\perls_learner_state\Plugin\XapiStateManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A controller class to send report form js to LRS server.
 *
 * @package Drupal\xapi_reporting\Controller
 */
class XapiReportingController extends ControllerBase {

  /**
   * Xapi state manager.
   *
   * @var \Drupal\perls_learner_state\Plugin\XapiStateManager
   */
  protected $stateManager;

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
   * Xapi reporting controller.
   *
   * @param \Drupal\perls_learner_state\Plugin\XapiStateManager $state_manager
   *   State manager service.
   * @param \Drupal\node\NodeStorageInterface $node_storage
   *   Node storage service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The drupal logger service.
   */
  public function __construct(XapiStateManager $state_manager, NodeStorageInterface $node_storage, LoggerChannelFactoryInterface $logger) {
    $this->stateManager = $state_manager;
    $this->nodeStorage = $node_storage;
    $this->moduleLogger = $logger->get('perls_xapi_reporting');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.state_manager'),
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get('logger.factory')
    );
  }

  /**
   * Create a sendable statement. Helper path of js reporting.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A symfony request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty Response object.
   */
  public function prepareSend(Request $request) {
    $content = json_decode($request->getContent());
    if (!is_array($content)) {
      $content = [$content];
    }
    foreach ($content as $data) {
      foreach ($data->state_ids as $state_id) {
        try {
          /** @var \Drupal\perls_learner_state\Plugin\XapiStateBase $state */
          $state = $this->stateManager->createInstance($state_id);
          if (is_numeric($data->content)) {
            $node = $this->nodeStorage->load($data->content);
            $state->setStatementContent($node);
            $state->processExtraData($data->extra_data);
            if (!$state->supportsContentType($node)) {
              return new Response("State plugin " . $state_id . " does not support content type", 400);
            }
            $state->getReadyStatement($node);
            $state->sendStatement();
          }

        }
        catch (PluginException $exception) {
          $this->moduleLogger->error('Unknown state api type: %id', ['%id' => $state_id]);
        }
      }
    }
    return new Response();
  }

}
