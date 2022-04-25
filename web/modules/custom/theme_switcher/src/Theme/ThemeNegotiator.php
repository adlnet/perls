<?php

namespace Drupal\theme_switcher\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * A theme negotiator which set the proper theme.
 *
 * The class use two params to decide which theme should load for a page.
 * The user's role and the visited page's path.
 */
class ThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The helper service which simplify the theme negotiation.
   *
   * @var \Drupal\theme_switcher\Theme\PathHelperInterface
   */
  protected $pathHelper;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Initialise the theme negotiators.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\theme_switcher\Theme\PathHelperInterface $path_helper
   *   The helper service which help to decides which theme we need to use.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user who visit the path.
   */
  public function __construct(ConfigFactoryInterface $config_factory, PathHelperInterface $path_helper, AccountInterface $current_user) {
    $this->configFactory = $config_factory;
    $this->pathHelper = $path_helper;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    if ($route_match->getRouteObject()) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    if ($theme_name = $this->pathHelper->pageThemeSwitcher()) {
      return $theme_name;
    }
    else {
      return $this->configFactory->get('system.theme')->get('default');
    }
  }

}
