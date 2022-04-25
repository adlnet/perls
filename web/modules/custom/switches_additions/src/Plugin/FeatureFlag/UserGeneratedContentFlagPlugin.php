<?php

namespace Drupal\switches_additions\Plugin\FeatureFlag;

use Drupal\achievements\Entity\AchievementEntity;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\badges\BadgePluginManager;
use Drupal\switches_additions\FeatureFlagPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides annotation for user generated content feature flag.
 *
 * @FeatureFlag(
 *   id = "user_generated_content_feature",
 *   label = @Translation("Enables and disables user generated content."),
 *   switchId = "user_generated_content",
 *   supportedManagerInvokeMethods = {
 *     "entityAccess",
 *     "entityCreateAccess",
 *     "getSwitchFeatureRoutes",
 *     "viewAccess",
 *     "achievementInfoAlter",
 *   },
 *   weight = "1",
 * )
 */
class UserGeneratedContentFlagPlugin extends FeatureFlagPluginBase implements ContainerFactoryPluginInterface {

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
   * @var \Drupal\Core\Menu\MenuLocalTaskManagerInterface
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
   * User generated content feature flag constructor.
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
   * {@inheritdoc}
   */
  public function featureWasDisabled() {
    $badge_service = \Drupal::service('badges.badge_service');
    if ($achievement = $badge_service->getAchievementById('authored_first_article')) {
      $achievement->delete();
    }
    if ($achievement = $badge_service->getAchievementById('published_first_article')) {
      $achievement->delete();
    }
    if ($achievement = $badge_service->getAchievementById('first_authored_completion')) {
      $achievement->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function featureWasEnabled() {
    $this::installUserGeneratedContentBadges();
  }

  /**
   * List of routes which will be checked in viewAccess function.
   *
   * @return string[]
   *   List of drupal routes.
   */
  public function getSwitchFeatureRoutes() {
    return [
      'view.my_content.page_1',
    ];
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
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Hide unpublished Nodes and block update/delete access.
    if ($entity->getEntityTypeId() !== 'node' && $entity->bundle() !== 'learn_article') {
      return AccessResult::neutral();
    }
    // When switch is enabled give access to view, update, delete
    // content in published and unpublished state to all users.
    if (
      $entity->getOwner()->id() === $account->id()
      && in_array($operation, ['view', 'update', 'delete'])
      && !$this->isSwitchDisabled()
      ) {
      return AccessResult::allowed()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($this->getSwitch());
    }
    return AccessResult::neutral();
  }

  /**
   * Checks user has permission to create content.
   */
  public function entityCreateAccess(AccountInterface $account, array $context, $entity_bundle) {
    if (
      $context['entity_type_id'] === 'node'
      && $entity_bundle === 'learn_article'
      && !$this->isSwitchDisabled()
    ) {
      return AccessResult::allowed()
        ->addCacheableDependency($this->getSwitch());
    }
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function viewAccess(AccountInterface $account, RouteMatchInterface $route_match) {
    $route = $route_match->getRouteName();
    if (in_array($route, $this->getSwitchFeatureRoutes())) {
      $result = $this->isSwitchDisabled() ? AccessResult::forbidden() : AccessResult::allowed();
      return $result->addCacheableDependency($this->getSwitch());
    }
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function achievementInfoAlter(&$re_info) {
    if ($this->isSwitchDisabled()) {
      if (isset($re_info['authored_count_badge_plugin'])) {
        unset($re_info['authored_count_badge_plugin']);
      }
      if (isset($re_info['authored_count_completed_badge_plugin'])) {
        unset($re_info['authored_count_completed_badge_plugin']);
      }
    }
  }

  /**
   * Install user generated content badges.
   */
  public static function installUserGeneratedContentBadges() {
    try {
      // First article to review.
      $badge = AchievementEntity::create([
        'id' => 'authored_first_article',
        'label' => 'First Article Sent for Review',
        'description' => 'Awarded to any user who creates their first article and submits it for review.',
        'storage' => NULL,
        'secret' => FALSE,
        'invisible' => FALSE,
        'manaual_only' => NULL,
        'points' => 1,
        'use_default_image' => FALSE,
        'locked_image_path' => 'public://badges/lockedBadge.png',
        'unlocked_image_path' => 'public://badges/1_review.png',
        'third_party_settings' => [
          'badges' => [
            'bundle' => 'badge',
            'plugin_id' => 'authored_count_badge_plugin',
            'authored_count_badge_plugin' => [
              'required_count' => 1,
              'selected_bundles' => [
                'learn_article' => 'learn_article',
              ],
              'selected_states' => [
                'review' => 'review',
              ],
            ],
            'badge_type' => 'Authored Count',
          ],
        ],
      ]);
      $badge->save();
      // First article to published.
      $badge = AchievementEntity::create([
        'id' => 'published_first_article',
        'label' => 'First Article Published',
        'description' => 'Awarded to any user when their first article is published.',
        'storage' => NULL,
        'secret' => FALSE,
        'invisible' => FALSE,
        'manaual_only' => NULL,
        'points' => 1,
        'use_default_image' => FALSE,
        'locked_image_path' => 'public://badges/lockedBadge.png',
        'unlocked_image_path' => 'public://badges/1_published.png',
        'third_party_settings' => [
          'badges' => [
            'bundle' => 'badge',
            'plugin_id' => 'authored_count_badge_plugin',
            'authored_count_badge_plugin' => [
              'required_count' => 1,
              'selected_bundles' => [
                'learn_article' => 'learn_article',
              ],
              'selected_states' => [
                'published' => 'published',
              ],
            ],
            'badge_type' => 'Authored Count',
          ],
        ],
      ]);
      $badge->save();

      // First article to published.
      $badge = AchievementEntity::create([
        'id' => 'first_authored_completion',
        'label' => 'First Self-Authored Article Completed',
        'description' => 'Awarded to any user when they create an article and another user completes it.',
        'storage' => NULL,
        'secret' => FALSE,
        'invisible' => FALSE,
        'manaual_only' => NULL,
        'points' => 1,
        'use_default_image' => FALSE,
        'locked_image_path' => 'public://badges/lockedBadge.png',
        'unlocked_image_path' => 'public://badges/1_authored_completed.png',
        'third_party_settings' => [
          'badges' => [
            'bundle' => 'badge',
            'plugin_id' => 'authored_count_completed_badge_plugin',
            'authored_count_completed_badge_plugin' => [
              'required_count' => 1,
              'selected_bundles' => [
                'learn_article' => 'learn_article',
              ],
            ],
            'badge_type' => 'Authored Completed',
          ],
        ],
      ]);
      $badge->save();

    }
    catch (\Exception $e) {
    }

  }

}
