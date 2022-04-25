<?php

namespace Drupal\perls_api\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\perls_api\RequestInspector;

/**
 * Sets the theme to the learner theme for requests coming from the mobile app.
 */
class ThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * The current request stack.
   *
   * @var \Drupal\perls_api\RequestInspector
   */
  protected $requestInspector;

  /**
   * Initialise the theme negotiators.
   *
   * @param \Drupal\perls_api\RequestInspector $request_inspector
   *   Details about the current request.
   */
  public function __construct(RequestInspector $request_inspector) {
    $this->requestInspector = $request_inspector;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    if ($this->requestInspector->isMobileApp()) {
      return 'perls_learner';
    }

    return NULL;
  }

}
