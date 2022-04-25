<?php

namespace Drupal\veracity_vql;

use Psr\Log\LoggerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\veracity_vql\Event\VqlPreExecuteEvent;
use Drupal\veracity_vql\Event\VqlPostExecuteEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Communicates with the Veracity API.
 */
class VeracityApi implements VeracityApiInterface {

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * A shared Veracity Client for making API requets.
   *
   * @var \Drupal\veracity_vql\VeracityClient
   */
  protected $veracityClient;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new VeracityApi object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, LoggerInterface $logger, EventDispatcherInterface $event_dispatcher) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->veracityClient = self::getVeracityClient($config_factory, $http_client);
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function testConnection(string $endpoint, array $access_key): bool {
    $temp_client = new VeracityClient($this->httpClient, $endpoint, $access_key);
    $query = [
      'process' => [],
    ];

    // This will throw if there is a problem.
    $result = $temp_client->analyze($query);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function executeVql(string $query): array {
    try {
      $pre_event = new VqlPreExecuteEvent($query);
      $this->eventDispatcher->dispatch(VqlPreExecuteEvent::EVENT_NAME, $pre_event);

      $result = $this->veracityClient->analyze($pre_event->prepareQuery()) ?? [];

      $post_event = new VqlPostExecuteEvent($query, $result);
      $this->eventDispatcher->dispatch(VqlPostExecuteEvent::EVENT_NAME, $post_event);
      return $post_event->getResult();
    }
    catch (\Exception $e) {
      $this->logger->warning('Encountered an error executing a VQL query: %error', ['%error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoint(): string {
    return $this->veracityClient->getEndpoint();
  }

  /**
   * {@inheritdoc}
   */
  public function getVqlRenderer(): string {
    $host = parse_url($this->veracityClient->getEndpoint(), PHP_URL_HOST);
    return "https://$host/integrations/public/vqlUtils/renderer.js";
  }

  /**
   * Creates a Veracity client.
   */
  protected static function getVeracityClient(ConfigFactoryInterface $config_factory, ClientInterface $http_client): VeracityClient {
    ['endpoint' => $endpoint, 'access_key' => $access_key] = self::getVeracityConnectionConfig($config_factory);

    return new VeracityClient($http_client, $endpoint, $access_key);
  }

  /**
   * Determines the appropriate endpoint and credentials based on config.
   */
  private static function getVeracityConnectionConfig(ConfigFactoryInterface $config_factory): array {
    // Check to see if there are integration-specific settings.
    $config = $config_factory->get('veracity_vql.settings');
    if (!empty($config->get('endpoint'))) {
      return [
        'endpoint' => $config->get('endpoint'),
        'access_key' => [
          $config->get('access_key_id'),
          $config->get('access_key_secret'),
        ],
      ];
    }

    $fallback_config = $config_factory->get('xapi.settings');
    return [
      'endpoint' => $fallback_config->get('lrs_url'),
      'access_key' => [
        $fallback_config->get('lrs_username'),
        $fallback_config->get('lrs_password'),
      ],
    ];
  }

}
