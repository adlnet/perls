<?php

namespace Drupal\veracity_vql;

use GuzzleHttp\RequestOptions;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * A client for communicating with Veracity.
 */
class VeracityClient {

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The base URL to Veracity.
   *
   * @var string
   */
  protected $endpoint;

  /**
   * The xAPI access key for Veracity.
   *
   * Array should contain two items:
   * - username
   * - password.
   *
   * @var array
   */
  protected $accessKey;

  /**
   * The API key for Veracity (not currently used).
   *
   * @var array
   */
  protected $apiKey;

  /**
   * Constructs a new VeracityClient.
   */
  public function __construct(ClientInterface $http_client, string $endpoint, array $access_key, array $api_key = []) {
    $this->httpClient = $http_client;
    $this->endpoint = self::parseEndpoint($endpoint);
    $this->accessKey = $access_key;
    $this->apiKey = $api_key;
  }

  /**
   * Retrieves the current endpoint.
   *
   * @return string
   *   The URL for the current Veracity endpoint.
   */
  public function getEndpoint(): string {
    return $this->endpoint;
  }

  /**
   * Requests the /analyze endpoint to execute VQL.
   *
   * @param array|string $query
   *   The VQL to execute.
   *
   * @return array|null
   *   The result.
   */
  public function analyze($query): ?array {
    if (is_array($query)) {
      $query = json_encode($query);
    }
    elseif (!is_string($query)) {
      throw new \InvalidArgumentException('query must be either a serializable object or string');
    }

    return $this->post('analyze', $query);
  }

  /**
   * Normalizes the endpoint.
   *
   * @param string $url
   *   The endpoint.
   *
   * @return string
   *   The normalized endpoint.
   */
  protected static function parseEndpoint(string $url): string {
    return rtrim($url, '/');
  }

  /**
   * Executes a POST request on the Veracity API.
   *
   * @param string $path
   *   The path to request.
   * @param string $body
   *   The request body.
   * @param string $content_type
   *   The body type (defaults to application/json).
   *
   * @return array|null
   *   The response.
   */
  protected function post(string $path, string $body, string $content_type = 'application/json'): ?array {
    $options = [
      RequestOptions::BODY => $body,
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
    ];

    return $this->send('POST', $path, $options);
  }

  /**
   * Sends an arbitrary HTTP request to the Veracity API.
   *
   * @param string $method
   *   The HTTP method.
   * @param string $path
   *   The path to request.
   * @param array $options
   *   Options for the request (see Guzzle).
   *
   * @return array|null
   *   The parsed response.
   */
  protected function send(string $method, string $path, array $options = []): ?array {
    $url = $this->endpoint . '/' . $path;
    $options += [
      RequestOptions::AUTH => $this->accessKey,
    ];

    try {
      $response = $this->httpClient->request($method, $url, $options);
    }
    catch (RequestException $e) {
      // Try and parse the response body for an error message.
      if ($e->hasResponse()) {
        if ($e->getResponse()->getBody()) {
          $response = json_decode($e->getResponse()->getBody()->getContents(), TRUE);
          if (isset($response['message'])) {
            throw new VeracityClientException($response['message']);
          }
        }
        if ($e->getResponse()->getStatusCode() == 404) {
          throw new VeracityClientException('Unable to find Veracity');
        }
      }

      throw $e;
    }

    $result = $response->getBody()->getContents();
    return json_decode($result, TRUE);
  }

}
