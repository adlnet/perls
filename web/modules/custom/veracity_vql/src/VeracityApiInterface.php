<?php

namespace Drupal\veracity_vql;

/**
 * Veracity API communication.
 */
interface VeracityApiInterface {

  /**
   * Tests a provided endpoint and access key for connection with Veracity.
   *
   * @param string $endpoint
   *   The Veracity endpoint.
   * @param array $access_key
   *   The access key; [0] = username, [1] = password.
   *
   * @return bool
   *   True if the connection was successful.
   */
  public function testConnection(string $endpoint, array $access_key): bool;

  /**
   * Executes a VQL query and returns the result.
   *
   * @param string $query
   *   The query to execute.
   *
   * @return array
   *   The result.
   */
  public function executeVql(string $query): array;

  /**
   * Returns the current Veracity API endpoint.
   *
   * @return string
   *   The URL for the current endpoint.
   */
  public function getEndpoint(): string;

  /**
   * Retrieves the URL to the VQL renderer on the configured Veracity instance.
   *
   * @return string
   *   The URL to the VQL rendering library.
   */
  public function getVqlRenderer(): string;

}
