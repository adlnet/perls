<?php

namespace Drupal\perls_api\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\perls_api\UserStatistics;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get stats for the current authenticated user.
 *
 * @RestResource(
 *   id = "user_stats_resource",
 *   label = @Translation("User stats resource"),
 *   uri_paths = {
 *     "canonical" = "/api/stats"
 *   }
 * )
 */
class UserStatsResource extends ResourceBase {

  /**
   * The user statistics service.
   *
   * @var \Drupal\perls_api\UserStatistics
   */
  protected $userStat;

  /**
   * Current logged in drupal user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new UserStatsResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current logged in user.
   * @param \Drupal\perls_api\UserStatistics $user_statistics
   *   The user statistics service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    UserStatistics $user_statistics) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->userStat = $user_statistics;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('perls_api'),
      $container->get('current_user'),
      $container->get('perls_api.user_statistics')
    );
  }

  /**
   * Retrieves and formats the current user's stats.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The current user's stats.
   */
  public function get() {
    $stats = $this->userStat->getUserStatistics($this->currentUser);

    $response = new ResourceResponse($stats, 200);
    $response->addCacheableDependency($this->currentUser);
    $response->getCacheableMetadata()->addCacheContexts(['user']);
    $response->getCacheableMetadata()->addCacheTags(['flagging_list']);

    return $response;
  }

}
