<?php

namespace Drupal\switches_additions\Plugin\FeatureFlag;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\switches_additions\FeatureFlagPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Managed the access to event feature.
 *
 * @FeatureFlag(
 *   id = "events",
 *   label = @Translation("Scheduled events"),
 *   switchId = "events",
 *   supportedManagerInvokeMethods = {
 *     "entityAccess",
 *     "entityCreateAccess",
 *     "getSwitchFeatureRoutes",
 *     "viewAccess"
 *   },
 *   weight = "3",
 * )
 */
class EventsFeatureFlag extends FeatureFlagPluginBase implements ContainerFactoryPluginInterface {


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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->menuCache = $container->get('cache.menu');
    $instance->menuLinkManager = $container->get('plugin.manager.menu.link');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->logger = $container->get('logger.factory')->get('switches_additions');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function featureWasDisabled() {
    // UnPublish events if any published when the switch is disabled.
    $this->unPublishEvents();
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
      'view.manage_events.page',
      'view.administrate_user_flags.administer_user_flags_attend',
    ];
  }

  /**
   * Checks user has permission to create a new event content.
   */
  public function entityCreateAccess(AccountInterface $account, array $context, $entity_bundle) {
    if ($entity_bundle === 'event' && $context['entity_type_id'] === 'node') {
      return AccessResult::forbiddenIf($this->isSwitchDisabled(), 'This feature is not enabled');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($entity->bundle() === 'event' && $entity->getEntityTypeId() === 'node') {
      return AccessResult::forbiddenIf($this->isSwitchDisabled(), 'This feature is not enabled');
    }

    return AccessResult::neutral();
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
    if (in_array($route_match->getRouteName(), $this->getSwitchFeatureRoutes())) {
      if ($this->isSwitchDisabled()) {
        return AccessResult::forbidden('This feature is not enabled');
      }
      else {
        return AccessResult::allowed();
      }
    }
    return AccessResult::neutral();
  }

  /**
   * Set events unpublished.
   */
  private function unPublishEvents() {
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'event')
      ->condition('status', '1')
      ->execute();

    $storage_handler = $this->entityTypeManager->getStorage('node');
    $nodes = $storage_handler->loadMultiple($nids);
    foreach ($nodes as $node) {
      // UnPublish nodes.
      $node->setUnpublished()->save();
      $this->logger->notice('%event has been unpublished because the Events feature has been disabled.', [
        '%event' => $node->label(),
        'link' => $node->toLink(t('view event'))->toString(),
      ]);
    }
  }

}
