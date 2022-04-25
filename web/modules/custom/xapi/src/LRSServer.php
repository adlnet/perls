<?php

namespace Drupal\xapi;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * This class help to communicate with LRS server.
 */
class LRSServer {

  /**
   * The LRS server url.
   *
   * @var string
   *   The server url.
   */
  protected $serverUrl;

  /**
   * The authentication to server.
   *
   * @var string
   */
  protected $auth;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Creates an LRS connection.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client) {
    $config = $config_factory->get('xapi.settings');
    // Data from the database.
    $raw_data = $config_factory->get('xapi.settings')->getRawData();
    // Invalidate raw data if any fields are empty.
    if (empty($raw_data) || empty($raw_data['lrs_url']) || empty($raw_data['lrs_username']) || empty($raw_data['lrs_password'])) {
      $raw_data = NULL;
    }
    $this->serverUrl = (!empty($raw_data)) ? $raw_data['lrs_url'] : $config->get('lrs_url');
    $this->httpClient = $http_client;
    $lrs_username = (!empty($raw_data)) ? $raw_data['lrs_username'] : $config->get('lrs_username');
    $lrs_password = (!empty($raw_data)) ? $raw_data['lrs_password'] : $config->get('lrs_password');
    $this->auth = 'Basic ' . base64_encode($lrs_username . ':' . $lrs_password);
  }

  /**
   * Send the request to LRS server.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A Symfony request.
   * @param string $timeout
   *   A timeout setting for request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A json object.
   */
  public function sendRequest(SymfonyRequest $request, $timeout = NULL) {
    if (empty($request)) {
      $this->setResponse('Empty request', ['Content-Type' => 'application/json'], 400);
    }

    $prepared_header = $this->prepareHeader($request->headers);

    if ($validation_fail = $this->validateServerSettings($prepared_header)) {
      return $validation_fail;
    }

    // Send the request.
    $request_options = [
      'headers' => $prepared_header,
      'body' => $request->getContent(),
    ];

    if ($timeout) {
      $request_options['timeout'] = $timeout;
    }

    $endpoint_url = $this->serverUrl . substr($request->getRequestUri(), 5);

    try {
      $guzzleResponse = $this->httpClient->request($request->getMethod(), $endpoint_url, $request_options);
    }
    catch (GuzzleException $exception) {
      // We should retrieves the full exception message because Guzzle truncate.
      $full_message = ($exception->hasResponse() && $exception->getResponse()->getBody())
        ? $exception->getResponse()->getBody()->getContents()
        : t("Message: %message Trace: %trace",
        [
          '%message' => $exception->getMessage(),
          '%trace' => $exception->getTraceAsString(),
        ]
      );
      $code = $exception->getCode() ? $exception->getCode() : 500;
      return $this->setResponse($full_message, [], $code);
    }
    return $this->setResponse($guzzleResponse->getBody(), $guzzleResponse->getHeaders(), $guzzleResponse->getStatusCode());
  }

  /**
   * Set a proper Json response.
   *
   * @param \Psr\Http\Message\StreamInterface|string $body_response
   *   The body part of a response.
   * @param array $response_header
   *   The response header.
   * @param string $status_code
   *   The status code of a response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A json object.
   */
  public function setResponse($body_response, array $response_header, $status_code) {
    $response = new SymfonyResponse();
    $response->setStatusCode($status_code);
    $response->headers = new ResponseHeaderBag($response_header);
    $response->setContent($body_response);

    if ($response->isClientError() && $status_code != SymfonyResponse::HTTP_NOT_FOUND) {
      // No need to log about a 404.
      // We expect a 404 when querying documents or statements.
      \Drupal::logger('xapi')->warning('LRS return error @status_code: @response', [
        '@status_code' => $status_code,
        '@response' => (string) $response,
      ]);
    }
    elseif ($response->isServerError()) {
      \Drupal::logger('xapi')->warning('LRS return error @status_code: @response', [
        '@status_code' => $status_code,
        '@response' => (string) $response,
      ]);
    }

    return $response;
  }

  /**
   * Prepare the request's header before every LRS request.
   *
   * @param \Symfony\Component\HttpFoundation\HeaderBag $request_headers
   *   The request header.
   *
   * @return array
   *   The request header.
   */
  public function prepareHeader(HeaderBag $request_headers) {
    global $base_url;

    $headers = [];

    foreach ($request_headers as $key => $value) {
      // This seems to always be true, but we don't want to reset a non-array.
      if (is_array($value)) {
        $headers[$key] = reset($value);
      }
    }

    // Don't send the Drupal cookie to the LRS.
    unset($headers["cookie"]);

    // Don't send this host to the LRS.
    unset($headers["host"]);

    // Don't send whatever this is.
    unset($headers["x-php-ob-level"]);

    // Replace the eLearning URL with the global URL value.
    $headers['referer'] = $base_url;

    // Use the configured LRS authentication.
    $headers["authorization"] = $this->auth;

    if (empty($headers["content-length"])) {
      unset($headers["content-length"]);
    }

    return $headers;
  }

  /**
   * Check the server settings.
   *
   * @param array $request_header
   *   A request header array.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A json response.
   */
  public function validateServerSettings(array $request_header) {
    $response_header = ['content-type' => 'application/json'];

    if (!empty($request_header['content-type'])) {
      $response_header['content-type'] = $request_header['content-type'];
    }

    if (empty($this->serverUrl) || empty($this->auth)) {
      return $this->setResponse('The LRS proxy is not configured.', $response_header, 502);
    }
  }

}
