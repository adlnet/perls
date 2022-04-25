<?php

namespace Drupal\perls_api;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Checks the api request.
 */
class RequestInspector {

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new RequestInspector object.
   */
  public function __construct(
    RequestStack $request_stack,
    ConfigFactoryInterface $config_factory) {
    $this->requestStack = $request_stack;
    $this->configFactory = $config_factory;
  }

  /**
   * Determines whether the current request is coming from the mobile app.
   *
   * @return bool
   *   TRUE if the request is from the app.
   */
  public function isMobileApp() {
    $configured_user_agent = $this->configFactory->get('perls_api.settings')->get('user_agent');
    $user_agent = $this->requestStack->getCurrentRequest()->headers->get('User-Agent');
    // We should check that header contains "User-Agent" parameter
    // otherwise strpos throws error.
    if (!empty($user_agent) && !empty($configured_user_agent)) {
      return strpos($user_agent, $configured_user_agent) !== FALSE;
    }
    return FALSE;
  }

}
