<?php

namespace Drupal\perls_dashboard\Plugin\FeatureFlag;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\switches_additions\FeatureFlagPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Managed the access to new dashboard feature.
 *
 * @FeatureFlag(
 *   id = "new_dashboard_feature",
 *   label = @Translation("Handles feature flag for new dashboard."),
 *   switchId = "new_dashboard",
 *   supportedManagerInvokeMethods = {
 *   },
 *   weight = "3",
 * )
 */
class NewDashboardFeatureFlag extends FeatureFlagPluginBase implements ContainerFactoryPluginInterface {


  /**
   * Drupal menu cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $menuCache;

  /**
   * Menu link manager service.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * NewDashboardFeatureFlag constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Cache\CacheBackendInterface $menu_cache
   *   Drupal menu backend service.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager
   *   Drupal menu link manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CacheBackendInterface $menu_cache, MenuLinkManagerInterface $menu_link_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuCache = $menu_cache;
    $this->menuLinkManager = $menu_link_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('cache.menu'),
      $container->get('plugin.manager.menu.link')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function featureWasDisabled() {}

  /**
   * {@inheritdoc}
   */
  public function featureWasEnabled() {}

  /**
   * {@inheritdoc}
   */
  public function featureWasToggled() {
    parent::featureWasToggled();
    $this->menuCache->invalidateAll();
    $this->menuLinkManager->rebuild();
  }

}
