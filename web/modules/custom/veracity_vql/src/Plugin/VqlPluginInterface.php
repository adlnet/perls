<?php

namespace Drupal\veracity_vql\Plugin;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for VQL plugins.
 */
interface VqlPluginInterface extends PluginInspectionInterface, ConfigurableInterface, PluginFormInterface {

  /**
   * Gets the plugin label.
   *
   * @return string
   *   The plugin label.
   */
  public function getPluginLabel(): string;

  /**
   * Gets the plugin description.
   *
   * @return string
   *   The plugin description.
   */
  public function getPluginDescription(): ?string;

}
