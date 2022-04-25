<?php

namespace Drupal\switches_additions\Plugin\FeatureFlag;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\badges\BadgePluginManager;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\switches_additions\FeatureFlagPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides annotation for achievements feature flag.
 *
 * @FeatureFlag(
 *   id = "acheivements_feature",
 *   label = @Translation("Handles feature flag"),
 *   switchId = "achievements",
 *   supportedManagerInvokeMethods = {
 *     "formAlter",
 *     "entityAccess",
 *     "createEntityAccess",
 *     "getSwitchFeatureRoutes",
 *     "viewAccess",
 *     "achievementInfoAlter",
 *   },
 *   weight = "1",
 * )
 */
class AchievementFlagPlugin extends FeatureFlagPluginBase implements ContainerFactoryPluginInterface {

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
   * Menu local task manager service.
   *
   * @var \Drupal\Core\Menu\LocalTaskManagerInterface
   */
  protected $localTaskManager;

  /**
   * Badges plugin manager.
   *
   * @var \Drupal\badges\BadgePluginManager
   */
  protected $badgePluginManager;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * @param \Drupal\Core\Menu\LocalTaskManagerInterface $local_task_manager
   *   Drupal local task manager.
   * @param \Drupal\badges\BadgePluginManager $badge_plugin_manger
   *   Badges plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager.
   */
  public function __construct(array $configuration,
  $plugin_id,
  $plugin_definition,
  CacheBackendInterface $menu_cache,
  MenuLinkManagerInterface $menu_link_manager,
  LocalTaskManagerInterface $local_task_manager,
  BadgePluginManager $badge_plugin_manger,
  EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuCache = $menu_cache;
    $this->menuLinkManager = $menu_link_manager;
    $this->localTaskManager = $local_task_manager;
    $this->badgePluginManager = $badge_plugin_manger;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('plugin.manager.menu.link'),
      $container->get('plugin.manager.menu.local_task'),
      $container->get('plugin.manager.badge'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * List of routes which will be checked in viewAccess function.
   *
   * @return string[]
   *   List of drupal routes.
   */
  public function getSwitchFeatureRoutes() {
    return [
      'badges.user_badges',
      'badges.user_certificates',
      'badges.view_certificate',
      'achievements.achievements_controller_userAchievements',
      'entity.achievement_entity.collection',
      'entity.achievement_entity.add_form',
      'entity.achievement_entity.canonical',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function featureWasDisabled() {
    $menu_items = $this->menuLinkManager->loadLinksByRoute('badges.user_badges');
    foreach ($menu_items as $menu_item) {
      if ($menu_item instanceof MenuLinkContent) {
        $id = $menu_item->getPluginDefinition()['metadata']['entity_id'];
        $menu_link = $this->entityTypeManager->getStorage('menu_link_content')->load($id);
        $menu_link->enabled = 0;
        $menu_link->save();
      }
    }
    $menu_items = $this->menuLinkManager->loadLinksByRoute('badges.user_certificates');
    foreach ($menu_items as $menu_item) {
      if ($menu_item instanceof MenuLinkContent) {
        $id = $menu_item->getPluginDefinition()['metadata']['entity_id'];
        $menu_link = $this->entityTypeManager->getStorage('menu_link_content')->load($id);
        $menu_link->enabled = 0;
        $menu_link->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function featureWasEnabled() {
    $menu_items = $this->menuLinkManager->loadLinksByRoute('badges.user_badges');
    foreach ($menu_items as $menu_item) {
      if ($menu_item instanceof MenuLinkContent) {
        $id = $menu_item->getPluginDefinition()['metadata']['entity_id'];
        $menu_link = $this->entityTypeManager->getStorage('menu_link_content')->load($id);
        $menu_link->enabled = 1;
        $menu_link->save();
      }
    }
    $menu_items = $this->menuLinkManager->loadLinksByRoute('badges.user_certificates');
    foreach ($menu_items as $menu_item) {
      if ($menu_item instanceof MenuLinkContent) {
        $id = $menu_item->getPluginDefinition()['metadata']['entity_id'];
        $menu_link = $this->entityTypeManager->getStorage('menu_link_content')->load($id);
        $menu_link->enabled = 1;
        $menu_link->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function featureWasToggled() {
    parent::featureWasToggled();
    $this->menuCache->invalidateAll();
    $this->menuLinkManager->rebuild();
    $this->localTaskManager->clearCachedDefinitions();
    $this->badgePluginManager->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    if ($this->isSwitchDisabled() == FALSE) {
      return;
    }
    switch ($form_id) {
      case 'node_course_form':
      case 'node_course_edit_form':
        if (isset($form['award_certificate'])) {
          $form['award_certificate']['#disabled'] = TRUE;
          $form['award_certificate']['#type'] = 'hidden';
        }
        break;

      case 'achievements_admin_form':
        $form['general'] = [
          '#markup' => '<h2>' . $this->t('Note: Full feature disabled') . '</h2>',
          '#weight' => -200,
        ];
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($entity->getEntityTypeId() === 'achievement_entity' &&
      $this->isSwitchDisabled()) {
      return AccessResult::forbidden('This Feature has been disabled.');
    }
    return AccessResult::neutral();
  }

  /**
   * Checks user has permission to create a new achievement.
   */
  public function entityCreateAccess(AccountInterface $account, array $context, $entity_bundle) {
    if ($context['entity_type_id'] === 'achievement_entity' &&
      $this->isSwitchDisabled()) {
      return AccessResult::forbidden($this->t('This Feature has been disabled.'));
    }
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function viewAccess(AccountInterface $account, RouteMatchInterface $route_match) {
    $route = $route_match->getRouteName();
    if (in_array($route, $this->getSwitchFeatureRoutes())) {
      return AccessResult::allowedIf(!$this->isSwitchDisabled());
    }
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function achievementInfoAlter(&$re_info) {
    if ($this->isSwitchDisabled()) {
      $re_info = [];
    }
  }

}
