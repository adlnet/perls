<?php

namespace Drupal\healthcheck\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provide response to /ping path.
 *
 * @package Drupal\healthcheck\Controller
 */
class HealthCheckController extends ControllerBase {

  /**
   * We just need a 200 status, no content necessary.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Return empty response.
   */
  public function ping() {
    return new Response();
  }

}
