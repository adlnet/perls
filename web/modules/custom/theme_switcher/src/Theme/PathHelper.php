<?php

namespace Drupal\theme_switcher\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Session\AccountInterface;

/**
 * Help to set the proper theme based on role and path.
 */
class PathHelper implements PathHelperInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * Load the proper theme for a path.
   *
   * @param \Drupal\Core\Routing\AdminContext $admin_context
   *   The route admin context to determine whether the route is an admin one.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user who visit the path.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher service.
   */
  public function __construct(AdminContext $admin_context, AccountInterface $current_user, CurrentPathStack $current_path, ConfigFactoryInterface $config_factory, PathMatcherInterface $path_matcher) {
    $this->adminContext = $admin_context;
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->currentPath = $current_path;
    $this->pathMatcher = $path_matcher;
  }

  /**
   * {@inheritdoc}
   */
  public function pageThemeSwitcher() {
    $switcher_settings = $this->configFactory->get('theme_switcher.settings');
    if (!$switcher_settings->isNew()) {
      $settings = $switcher_settings->get();
      // Enforce choosing highest weight first.
      usort($settings['spt_table'], function ($a, $b) {
        if ($a['weight'] == $b['weight']) {
          return 0;
        }
        return ($a['weight'] > $b['weight']) ? -1 : 1;
      });

      $user_roles = $this->currentUser->getRoles();
      $path = \Drupal::service('path_alias.manager')->getAliasByPath($this->currentPath->getPath());

      foreach ($settings['spt_table'] as $setting) {
        if ($setting['status']) {
          $roles = array_filter($setting['roles']);
          if ($this->isMatchCurrentPath($setting['pages'], $roles, $path, $user_roles)) {
            return $setting['theme'];
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Checks that one record from theme switcher is match with current path.
   *
   * @param string $page_setting
   *   The page path setting form theme switcher record.
   * @param array $roles
   *   The role setting from theme switcher setting record.
   * @param string $path
   *   The path we are checking against.
   * @param array $user_roles
   *   The current users roles.
   *
   * @return bool
   *   It gives that one of record match with the current path.
   */
  public function isMatchCurrentPath($page_setting, array $roles, $path, array $user_roles) {
    $path_match = $this->pathMatcher->matchPath($path, $page_setting);
    return (bool) (array_intersect($roles, $user_roles)) && $path_match;
  }

}
