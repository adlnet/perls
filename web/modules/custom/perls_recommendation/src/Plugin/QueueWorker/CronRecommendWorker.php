<?php

namespace Drupal\perls_recommendation\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\perls_recommendation\RecommendService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Use cron to get recommendations from recommendation engine.
 *
 * @QueueWorker(
 *   id = "cron_recommendation_engine_recommend",
 *   title = @Translation("Recommendation Engine Recommend (cron)"),
 *   cron = {"time" = 10}
 * )
 */
class CronRecommendWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {


  /**
   * The user storage.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The recommendation service.
   *
   * @var \Drupal\perls_recommendation\RecommendService
   */
  protected $recommendService;

  /**
   * The Logger Service.
   *
   * @var Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Creates a new object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The node storage.
   * @param \Drupal\perls_recommendation\RecommendService $recommend_service
   *   The Recommendation Engine Service.
   * @param Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory for this class.
   */
  public function __construct(EntityManagerInterface $entity_manager, RecommendService $recommend_service, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityManager = $entity_manager;
    $this->recommendService = $recommend_service;
    $this->logger = $logger_factory->get('Recommendation Cron Recommend Worker');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity.manager'),
      $container->get('perls_recommendation.recommend'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!$data->id || !$data->type) {
      return FALSE;
    }
    if ($data->type != 'user') {
      // Only users can get recommendations.
      // return False and log error.
      $this->logger
        ->error('Entity of type ' . $data->type . ' is requesting recommendations. Only entity of type "user" accepted.');
      return FALSE;
    }
    $storage = $this->entityManager->getStorage($data->type);
    if (!$storage) {
      return FALSE;
    }

    $user = $storage->load($data->id);
    if ($user) {
      $this->recommendService->processUserRecommendations($user);
      $this->logger
        ->info('User ' . $user->id() . ' has been fetched recommendations from recommendation engine.');
    }
  }

}
