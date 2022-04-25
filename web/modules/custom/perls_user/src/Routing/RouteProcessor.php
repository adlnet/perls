<?php

namespace Drupal\perls_user\Routing;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\RouteProcessor\OutboundRouteProcessorInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\perls_dashboard\Services\NewDashboardHelper;
use Drupal\theme_switcher\Theme\PathHelperInterface;
use Symfony\Component\Routing\Route;

/**
 * Route processor to keep admins in the learner UI when requesting <front>.
 */
class RouteProcessor implements OutboundRouteProcessorInterface {

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Theme switcher evaluation.
   *
   * @var \Drupal\theme_switcher\Theme\PathHelperInterface
   */
  protected $pathHelper;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * Helper service of new dashboard.
   *
   * @var \Drupal\perls_dashboard\Services\NewDashboardHelper
   */
  protected $dashboardHelper;

  /**
   * Creates a new RouteProcessor object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\theme_switcher\Theme\PathHelperInterface $path_helper
   *   Reference to the theme switcher path helper.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   */
  public function __construct(
    AccountProxyInterface $current_user,
    PathHelperInterface $path_helper,
    RouteMatchInterface $route_match,
    ConfigFactoryInterface $config_factory,
    RouteProviderInterface $route_provider) {
    $this->currentUser = $current_user;
    $this->pathHelper = $path_helper;
    $this->routeMatch = $route_match;
    $this->configFactory = $config_factory;
    $this->routeProvider = $route_provider;
  }

  /**
   * Set the dashboard helper service.
   *
   * @param \Drupal\perls_dashboard\Services\NewDashboardHelper $dashboard_helper
   *   New dashboard helper service.
   */
  public function setDashboardHelper(NewDashboardHelper $dashboard_helper) {
    $this->dashboardHelper = $dashboard_helper;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($route_name, Route $route, array &$parameters, BubbleableMetadata $bubbleable_metadata = NULL) {
    $addCacheContext = FALSE;
    // Avoid making changes to the path if the current page is the front page.
    // In practice, this prevents a redirect path from being changed,
    // but still allows links rendered to the page to have their paths changed.
    if ($route_name === '<front>' && !$this->isFrontPage() && $this->currentUser->getRoles(TRUE) && $this->pathHelper->pageThemeSwitcher() === 'perls_learner') {
      $addCacheContext = TRUE;
      if (!$this->dashboardHelper->isNewDashboardActive()) {
        $route->setPath('/our_picks');
      }
      else {
        $route->setPath('/start');
      }
    }

    if ($addCacheContext  && $bubbleable_metadata != NULL) {
      $bubbleable_metadata->addCacheContexts(['theme', 'user.roles']);
    }
  }

  /**
   * Determines whether the current route is the front page.
   */
  protected function isFrontPage() {
    $route_object = $this->routeMatch->getRouteObject();
    return $route_object && $route_object->getPath() === $this->getFrontPage();
  }

  /**
   * Retrieve the path to the front page.
   */
  protected function getFrontPage() {
    return $this->configFactory->get('system.site')->get('page.front');
  }

}
