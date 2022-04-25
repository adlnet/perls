<?php

namespace Drupal\perls_dashboard\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Provides a resource to get the site dashboard.
 *
 * @RestResource(
 *   id = "site_dashboard_resource",
 *   label = @Translation("Dashboard"),
 *   uri_paths = {
 *     "canonical" = "/api/dashboard"
 *   }
 * )
 */
class SiteDashboardResource extends ResourceBase {

  const DASHBOARD_SETTINGS = 'new_dashboard-layout_builder-0';

  /**
   * Config entity normalizer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * Storage of page variant.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $variantStorage;

  /**
   * SiteDashboardResource constructor.
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
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   Serializer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    SerializerInterface $serializer,
    EntityTypeManagerInterface $entity_type_manager
    ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->serializer = $serializer;
    $this->variantStorage = $entity_type_manager->getStorage('page_variant');
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
      $container->get('serializer'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Response for get dashboard request.
   */
  public function get() {
    $renderer = \Drupal::service('renderer');
    $context = new RenderContext();
    $response = new ResourceResponse();
    $page_variant = $this->variantStorage->load(self::DASHBOARD_SETTINGS);
    if (empty($page_variant)) {
      $response->setContent('The dashboard configuration is not available');
      $response->setStatusCode(404);
      return $response;
    }
    else {
      $response = $renderer->executeInRenderContext($context, function () use ($renderer, $page_variant, $response) {
        $dashboard = $this->serializer->normalize($page_variant, 'json');
        $response->setContent(json_encode($dashboard->getNormalization()));
        $response->addCacheableDependency($dashboard);
        $response->getCacheableMetadata()->addCacheContexts(['user']);

        return $response;
      });
    }

    return $response;
  }

}
