<?php

namespace Drupal\vidyo_platform\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the Vidyo Room Renderer plugin manager.
 */
class VidyoRoomRendererPluginManager extends DefaultPluginManager {

  /**
   * Constructs a new VidyoRoomRendererPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/VidyoRoomRenderer', $namespaces, $module_handler, 'Drupal\vidyo_platform\Plugin\VidyoRoomRendererInterface', 'Drupal\vidyo_platform\Annotation\VidyoRoomRenderer');

    $this->alterInfo('vidyo_platform_vidyo_room_renderer_info');
    $this->setCacheBackend($cache_backend, 'vidyo_platform_vidyo_room_renderer_plugins');
  }

  /**
   * Create a list of available plugins.
   *
   * @param bool $include_descriptions
   *   Whether descriptions should be included on the option labels.
   *
   * @return array
   *   List of plugins.
   */
  public function getOptions($include_descriptions = FALSE) {
    return array_reduce($this->getDefinitions(), function ($result, $plugin) use ($include_descriptions) {
      $label = $plugin['label'];
      if ($include_descriptions) {
        $label .= '<br><small>' . $plugin['description'] . '</small>';
      }
      $result[$plugin['id']] = $label;
      return $result;
    }, []);
  }

}
