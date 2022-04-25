<?php

namespace Drupal\perls_dashboard\Services;

use Drupal\switches_additions\FeatureFlagPluginManager;

/**
 * Helper service for dashboard.
 */
class NewDashboardHelper {

  /**
   * Feature flag manager.
   *
   * @var \Drupal\switches_additions\FeatureFlagPluginManager
   */
  protected $featureFlagManager;

  /**
   * NewDashboardHelper constructor.
   *
   * @param \Drupal\switches_additions\FeatureFlagPluginManager $feature_flag_manager
   *   Feature flag manager.
   */
  public function __construct(FeatureFlagPluginManager $feature_flag_manager) {
    $this->featureFlagManager = $feature_flag_manager;
  }

  /**
   * Gives back that the new dashboard is activated.
   *
   * @return bool
   *   TRUE if activated otherwise FALSE.
   */
  public function isNewDashboardActive(): bool {
    /** @var \Drupal\perls_dashboard\Plugin\FeatureFlag\NewDashboardFeatureFlag $dashboard_feature */
    $dashboard_feature = $this->featureFlagManager->createInstance('new_dashboard_feature');
    return !$dashboard_feature->isSwitchDisabled();
  }

}
