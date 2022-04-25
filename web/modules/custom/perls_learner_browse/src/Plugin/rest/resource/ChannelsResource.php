<?php

namespace Drupal\perls_learner_browse\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource for returning Followed content for the current user.
 *
 * @RestResource(
 *   id = "channels_resource",
 *   label = @Translation("Followed content"),
 *   uri_paths = {
 *     "canonical" = "/api/channels"
 *   }
 * )
 */
class ChannelsResource extends ResourceBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new BrowseResource object.
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
   *   A current user instance.
   */
  public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        array $serializer_formats,
        LoggerInterface $logger,
        AccountProxyInterface $current_user
        ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

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
        $container->get('logger.factory')->get('rest'),
        $container->get('current_user')
    );
  }

  /**
   * Responds to GET requests.
   *
   * Builds a response containing relevant content for the current user.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws if the user does not have permission to access content.
   */
  public function get() {
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $collection = \Drupal::service('perls_learner_browse.followed_content')->getFollowedContent($this->currentUser);

    $response = new ResourceResponse($collection->groupedResults(), 200);
    // Add the collection as dependency.
    $response->addCacheableDependency($collection);
    // We also need to add flagging as a dependency to ensure correct operation.
    // This could be limited to just the following flagging
    // but not sure of tag that would need.
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->addCacheTags(\Drupal::service('entity_type.manager')->getDefinition('flagging')->getListCacheTags());
    $cache_metadata->addCacheContexts(\Drupal::service('entity_type.manager')->getDefinition('flagging')->getListCacheContexts());
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

}
