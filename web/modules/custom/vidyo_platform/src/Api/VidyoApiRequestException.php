<?php

namespace Drupal\vidyo_platform\Api;

/**
 * Represents an exception calling an API method.
 */
class VidyoApiRequestException extends VidyoApiException {
  /**
   * The headers on the failed request.
   *
   * @var string
   */
  protected $requestHeaders;
  /**
   * The request body of the failed request.
   *
   * @var string
   */
  protected $request;
  /**
   * The response headers on the failed request.
   *
   * @var string
   */
  protected $responseHeaders;
  /**
   * The response body on the failed request.
   *
   * @var string
   */
  protected $response;

  /**
   * Constructs an VidyoApiRequestException.
   */
  public function __construct(\SoapFault $fault, \SoapClient $client) {
    $this->requestHeaders = trim(preg_replace('/^Authorization:.*?$/m', '', $client->__getLastRequestHeaders()));
    $this->request = $client->__getLastRequest();
    $this->responseHeaders = $client->__getLastResponseHeaders();
    $this->response = $client->__getLastResponse();

    $code = 0;
    if (preg_match('/HTTP\/[\d.]+ (\d{3})/', $this->responseHeaders, $matches)) {
      $code = $matches[1];
    }

    parent::__construct($fault->getMessage(), $fault, $code);
  }

  /**
   * Attempts to determine the type of fault that occurred.
   *
   * @return string|null
   *   The type of fault.
   */
  public function getFaultType(): ?string {
    $detail = $this->getFault()->detail;
    if (!$detail) {
      return NULL;
    }
    $faults = array_keys((array) $detail);

    return implode(', ', $faults);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    $result = get_class($this) . ': ' . $this->getMessage() . PHP_EOL;
    if ($this->getCode()) {
      $result .= 'Code: ' . $this->getCode() . PHP_EOL;
    }

    $result .= PHP_EOL . 'Request:' . PHP_EOL;
    $result .= $this->requestHeaders . PHP_EOL;
    $result .= $this->request . PHP_EOL;
    $result .= PHP_EOL . 'Response:' . PHP_EOL;
    $result .= $this->responseHeaders . PHP_EOL;
    $result .= $this->response . PHP_EOL;

    $result .= PHP_EOL . 'Stack trace:' . PHP_EOL;
    $result .= $this->getTraceAsString() . PHP_EOL;

    return $result;
  }

  /**
   * Retrieves the original SOAPFault that occurred.
   */
  protected function getFault(): \SoapFault {
    return $this->getPrevious();
  }

}
