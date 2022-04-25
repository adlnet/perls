<?php

namespace Drupal\theme_switcher\Theme;

/**
 * Provides an interface for URL path matchers.
 */
interface PathHelperInterface {

  /**
   * Set the active theme based on theme switcher settings.
   */
  public function pageThemeSwitcher();

}
