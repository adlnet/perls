<?php

namespace Drupal\perls_podcast\Plugin\FeatureFlag;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\switches_additions\FeatureFlagPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Managed the access to podcast feature.
 *
 * @FeatureFlag(
 *   id = "podcast_feature",
 *   label = @Translation("Handles feature flag for podcast."),
 *   switchId = "podcast_support",
 *   supportedManagerInvokeMethods = {
 *     "entityAccess",
 *     "entityCreateAccess",
 *     "formAlter",
 *     "getSwitchFeatureRoutes",
 *     "viewAccess"
 *   },
 *   weight = "3",
 * )
 */
class PodcastFeatureFlag extends FeatureFlagPluginBase implements ContainerFactoryPluginInterface {


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
   * PodcastFeatureFlag constructor.
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
  public function featureWasDisabled() {
  }

  /**
   * {@inheritdoc}
   */
  public function featureWasEnabled() {
  }

  /**
   * {@inheritdoc}
   */
  public function featureWasToggled() {
    parent::featureWasToggled();
    $this->menuCache->invalidateAll();
    $this->menuLinkManager->rebuild();
  }

  /**
   * List of routes which will be checked in viewAccess function.
   *
   * @return string[]
   *   List of drupal routes.
   */
  public function getSwitchFeatureRoutes() {
    return [
      'view.manage_podcasts.page_1',
    ];
  }

  /**
   * Checks user has permission to create a new podcast content.
   */
  public function entityCreateAccess(AccountInterface $account, array $context, $entity_bundle) {
    if ($context['entity_type_id'] === 'node' &&
      in_array($entity_bundle, ['podcast_episode', 'podcast']) &&
      $this->isSwitchDisabled()) {
      return AccessResult::forbidden();
    }
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($entity->getEntityTypeId() === 'node' &&
      in_array($entity->bundle(), ['podcast_episode', 'podcast']) &&
      $this->isSwitchDisabled()) {
      return AccessResult::forbidden();
    }
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    if ($form_id === 'system_theme_settings') {
      $form['color']['palette']['podcast']['#access'] = !$this->isSwitchDisabled();
    }
  }

  /**
   * View access for a route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A Drupal account.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route what the user checks right now.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Decide the actual user has access to page or doesn't.
   */
  public function viewAccess(AccountInterface $account, RouteMatchInterface $route_match) {
    if (!empty($route_match->getParameter('view_id')) &&
      $route_match->getParameter('view_id') === 'manage_podcasts') {
      return AccessResult::allowedIf(!$this->isSwitchDisabled());
    }
    return AccessResult::neutral();
  }

}
