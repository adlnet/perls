<?php

namespace Drupal\xapi;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\user\UserInterface;

/**
 * Manage the different xapi actor IFI types.
 */
class XapiActorIFIManager extends DefaultPluginManager {

  /**
   * Plugin id of default ifi type.
   */
  const DEFAULT_IFI_TYPE = 'account';

  /**
   * LRS xapi settings.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $xapiSettings;

  /**
   * Constructs a XapiActorIFIManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    ConfigFactoryInterface $config_factory) {
    parent::__construct(
      'Plugin/XapiActorIFI',
      $namespaces,
      $module_handler,
      'Drupal\xapi\XapiActorIFIPluginBase',
      'Drupal\xapi\Annotation\XapiActorIFI'
    );
    $this->alterInfo('xapi_actor_type_info');
    $this->setCacheBackend($cache_backend, 'xapi_actor_type_info_plugins');
    $this->xapiSettings = $config_factory->get('xapi.settings');
  }

  /**
   * Create a list of available plugins.
   *
   * @return array
   *   List of IFI plugins.
   */
  public function getOptions() {
    $ifi_list = [];
    foreach ($this->getDefinitions() as $plugin) {
      $ifi_list[$plugin['id']] = $plugin['label'];
    }

    return $ifi_list;
  }

  /**
   * Load the active ifi.
   *
   * @param \Drupal\user\UserInterface $user
   *   A Drupal user.
   *
   * @return array
   *   The IFI of an actor.
   */
  public function getActiveIfi(UserInterface $user) {
    $plugin_id = $this->xapiSettings->get('xapi_actor_ifi') ?? self::DEFAULT_IFI_TYPE;
    return $this->createInstance($plugin_id)->getIfi($user);
  }

}
