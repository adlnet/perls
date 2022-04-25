<?php

namespace Drupal\veracity_vql\Plugin;

use Drupal\Core\Logger\LoggerChannelTrait;

/**
 * Convenience methods for retrieving configured plugin instances.
 */
trait VqlPluginManagerTrait {

  use LoggerChannelTrait;

  /**
   * Retrieves configured plugins.
   *
   * @param array $configuration
   *   Configuration to apply to the plugin instances (keyed by plugin id).
   * @param bool $includeDisabled
   *   Whether to include plugins that are disabled by the configuration.
   *
   * @return array
   *   An array of configured plugins, keyed by plugin ID.
   */
  public function getPlugins(array $configuration = [], bool $includeDisabled = TRUE): array {
    $plugins = [];

    foreach ($this->getDefinitions() as $plugin_id => $plugin_definition) {
      $plugin_configuration = $configuration[$plugin_id] ?? [];
      if (!$includeDisabled && (!isset($plugin_configuration['status']) || !$plugin_configuration['status'])) {
        continue;
      }

      try {
        $plugins[$plugin_id] = $this->createInstance($plugin_id, $plugin_configuration);
      }
      catch (PluginNotFoundException $e) {
        $this->getLogger('veracity_vql')->warning('Plugin @plugin_id not found', ['@plugin_id' => $plugin_id]);
      }
    }

    return $plugins;
  }

}
