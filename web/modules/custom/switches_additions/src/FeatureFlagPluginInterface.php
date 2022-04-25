<?php

namespace Drupal\switches_additions;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines the required interface for all feature flag plugins.
 *
 * @ingroup feature_flag_api
 */
interface FeatureFlagPluginInterface extends ConfigurableInterface, DependentPluginInterface, PluginFormInterface, PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Triggered when feature is toggled.
   */
  public function featureWasToggled();

  /**
   * Triggered when feature is disabled.
   */
  public function featureWasDisabled();

  /**
   * Triggered when feature is enabled.
   */
  public function featureWasEnabled();

}
