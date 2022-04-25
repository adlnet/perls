<?php

namespace Drupal\config_resource\Plugin\rest\resource;

use Drupal\Core\Config\ConfigFactory;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\config_resource\ExposedConfigNormalizerPluginManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to expose configurations in json format.
 *
 * @RestResource(
 *   id = "configuration_settings_resource",
 *   label = @Translation("Configuration Settings Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/configuration-settings"
 *   }
 * )
 */
class ExposedConfigResource extends ResourceBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The exposed config normalizer plugin manager.
   *
   * @var Drupal\config_resource\ExposedConfigNormalizerPluginManager
   */
  protected $configNormalizerManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('config_resource'),
      $container->get('config.factory'),
      $container->get('plugin.manager.exposed_config_normalizer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    ConfigFactory $config_factory,
    ExposedConfigNormalizerPluginManager $config_normalizer_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->configFactory = $config_factory;
    $this->configNormalizerManager = $config_normalizer_manager;
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   */
  public function get() {
    $exposed_configs = $this
      ->configFactory
      ->get('config_resource.settings')
      ->get('exposed_config_entities');
    $response_data = [];
    foreach ($exposed_configs as $config) {
      $response_data[$config] = $this->configFactory->get($config)->get();
    }
    // Run some alters on this data before returning it.
    $response_data = $this->alterOutput($response_data);

    $response = new ResourceResponse($response_data);
    $response->addCacheableDependency($exposed_configs);

    return $response;
  }

  /**
   * Function to run config alters.
   */
  protected function alterOutput(array $response_data) {
    $functions = $this->configNormalizerManager->getPlugins();
    $config_alters = $this
      ->configFactory
      ->get('config_resource.settings')
      ->get('exposed_config_entities_alters');

    foreach ($config_alters as $alter_function) {
      // Function should be of format - key function arg1 arg2 ...
      $details = explode('|', $alter_function, 3);
      if (count($details) < 2) {
        continue;
      }
      $args = [];
      if (isset($details[2])) {
        $args = explode('|', $details[2]);
      }
      if (isset($functions[$details[1]])) {
        $plugin = $functions[$details[1]];
        $response_data = $plugin->process($details[0], $response_data, $args);
      }
    }
    return $response_data;
  }

}
