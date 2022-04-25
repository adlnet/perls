<?php

namespace Drupal\vidyo_platform\Api\Client;

use Drupal\vidyo_platform\Api\VidyoApiException;
use Drupal\vidyo_platform\Api\VidyoApiRequestException;

/**
 * Web service client for interacting with the VidyoPortal User Service.
 *
 * The VidyoPortal User Service is a SOAP API. Although there doesn't appear
 * to be any methods on this class, the methods are generated at runtime
 * when it retrieves the WSDL.
 *
 * @see https://support.vidyocloud.com/hc/en-us/articles/115000942153-Overview-of-User-APIs
 * @see https://support.vidyocloud.com/hc/en-us/articles/360007515433-Web-Services-API-User-Guides
 */
class UserServiceClient extends \SoapClient {
  const ENDPOINT = 'services/v1_1/VidyoPortalUserService';

  /**
   * The base URL for the VidyoPortal.
   *
   * @var string
   */
  protected $baseUrl;

  /**
   * Constructs a new UserServiceClient.
   *
   * @param string $base_url
   *   The base URL of the API.
   * @param string $username
   *   A username for a portal user.
   * @param string $password
   *   The password for the portal user.
   * @param array $options
   *   Additional options to pass to the PHP SOAPClient.
   */
  public function __construct(string $base_url, string $username, string $password, array $options = []) {
    $this->baseUrl = $base_url;

    $url = rtrim($base_url, '/') . '/' . static::ENDPOINT;
    $wsdl = $url . '?wsdl';

    $options += [
      'cache_wsdl' => WSDL_CACHE_DISK,
      'location' => $url,
      'login' => $username,
      'password' => $password,
      'trace' => TRUE,
      'exceptions' => TRUE,
      'connection_timeout' => 10,
      'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
      'classmap' => [
        'Entity' => 'Drupal\vidyo_platform\Api\Model\Room',
        'RoomMode' => 'Drupal\vidyo_platform\Api\Model\RoomMode',
      ],
    ];

    try {
      @parent::__construct($wsdl, $options);
    }
    catch (\SoapFault $e) {
      throw new VidyoApiException($e->getMessage(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __call($name, $args) {
    try {
      return parent::__call($name, $args);
    }
    catch (\SoapFault $e) {
      throw new VidyoApiRequestException($e, $this);
    }
  }

}
