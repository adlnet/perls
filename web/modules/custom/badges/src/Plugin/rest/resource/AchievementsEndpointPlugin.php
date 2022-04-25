<?php

namespace Drupal\badges\Plugin\rest\resource;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\badges\Service\BadgeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "available_badges_api",
 *   label = @Translation("Available Badges API"),
 *   uri_paths = {
 *     "canonical" = "/api/badges",
 *   }
 * )
 */
class AchievementsEndpointPlugin extends ResourceBase implements ContainerFactoryPluginInterface {

  /**
   * The badge Service.
   *
   * @var \Drupal\badges\Service\BadgeService
   */
  protected $badgeService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('badges.badge_service'),
      $container->get('current_user')
    );
  }

  /**
   * Constructor for Achievements Endpoint.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    BadgeService $badge_service,
    AccountInterface $current_user
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->badgeService = $badge_service;
    $this->currentUser = $current_user;
  }

  /**
   * Responds to GET request.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function get() {
    if (!$this->currentUser) {
      throw new AccessDeniedHttpException('You are not authorized to access this resource.');
    }
    // Get a list of all badges.
    $data = $this->badgeService->listAchievements($this->currentUser, FALSE, 'badge');
    // Mark these as seen so user doesn't get popups.
    $this->badgeService->markAchievementsAsSeen($this->currentUser->id(), array_keys($data));
    $response = new ModifiedResourceResponse(array_values($data), 200);
    return $response;
  }

}
