<?php

namespace Drupal\perls_group_management\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Group;
use Drupal\perls_group_management\GroupStatistics;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Group Statistics block.
 *
 * @Block(
 *   id = "group_statistics_block",
 *   admin_label = @Translation("Group Statistics")
 * )
 */
class GroupStatisticsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The group statistics service.
   *
   * @var \Drupal\perls_group_management\GroupStatistics
   */
  private $groupStatistics;

  /**
   * Route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  private $routeMatch;

  /**
   * Provides a group statistics block for individual groups.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\perls_group_management\GroupStatistics $group_statistics
   *   A helper service to collect the group statistics.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match helper.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    GroupStatistics $group_statistics,
    RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->groupStatistics = $group_statistics;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('perls_group_management.group_statics'),
      $container->get('current_route_match')
    );
  }

  /**
   * Renders view display blocks.
   */
  public function build() {
    $group = $this->routeMatch->getParameter('group');
    $build['#cache'] = [
      'contexts' => [
        'route.group',
      ],
    ];

    if (!$group instanceof Group) {
      return $build;
    }

    // View: Group Statistics - Number of members.
    $build['group_statics_members_wrapper'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Members'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['group-statistics-members'],
      ],
    ];

    $build['group_statics_members_wrapper']['member_number'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Total: :number', [
        ':number' => $this->groupStatistics->numberOfMembers($group->id()),
      ]),
      '#suffix' => '</div>',
    ];

    $build['group_statics_seen_wrapper'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Viewed'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['group-statistics-seen'],
      ],
    ];

    // View: Group Statistics - Today seen items.
    $build['group_statics_seen_wrapper']['items_seen_today'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Today: :number', [
        ':number' => $this->groupStatistics->seenContentStatics($group->id(), 'today'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Group Statistics - Weekly seen items.
    $build['group_statics_seen_wrapper']['items_seen_week'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Week: :number', [
        ':number' => $this->groupStatistics->seenContentStatics($group->id(), 'week'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Group Statistics - Monthly seen items.
    $build['group_statics_seen_wrapper']['items_seen_month'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Month: :number', [
        ':number' => $this->groupStatistics->seenContentStatics($group->id(), 'month'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Group Statistics - Seen all items.
    $build['group_statics_seen_wrapper']['items_seen_all'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Total: :number', [
        ':number' => $this->groupStatistics->seenContentStatics($group->id()),
      ]),
      '#suffix' => '</div>',
    ];

    $build['group_statics_completed_wrapper'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Completed'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['group-statistics-completed'],
      ],
    ];

    // View: Group Statistics - Completed content today.
    $build['group_statics_completed_wrapper']['items_completed_today'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Today: :number', [
        ':number' => $this->groupStatistics->flaggedContentStatics($group->id(), 'completed', 'today'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Group Statistics - Completed content in this week.
    $build['group_statics_completed_wrapper']['items_completed_week'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Week: :number', [
        ':number' => $this->groupStatistics->flaggedContentStatics($group->id(), 'completed', 'week'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Group Statistics - Completed content in this month.
    $build['group_statics_completed_wrapper']['items_completed_month'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Month: :number', [
        ':number' => $this->groupStatistics->flaggedContentStatics($group->id(), 'completed', 'month'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Group Statistics - All Completed content.
    $build['group_statics_completed_wrapper']['items_completed_all'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Total: :number', [
        ':number' => $this->groupStatistics->flaggedContentStatics($group->id(), 'completed'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Group Statistics - Bookmarked content today.
    $build['group_statics_bookmarked_wrapper'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Bookmarked'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['group-statistics-bookmarked'],
      ],
    ];

    $build['group_statics_bookmarked_wrapper']['items_bookmarked_today'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Today: :number', [
        ':number' => $this->groupStatistics->flaggedContentStatics($group->id(), 'bookmark', 'today'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Group Statistics - Bookmarked content in this week.
    $build['group_statics_bookmarked_wrapper']['items_bookmarked_week'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Week: :number', [
        ':number' => $this->groupStatistics->flaggedContentStatics($group->id(), 'bookmark', 'week'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Group Statistics - Bookmarked content in this month.
    $build['group_statics_bookmarked_wrapper']['items_bookmarked_month'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Month: :number', [
        ':number' => $this->groupStatistics->flaggedContentStatics($group->id(), 'bookmark', 'month'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Group Statistics - All bookmarked content.
    $build['group_statics_bookmarked_wrapper']['items_bookmarked_all'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Total: :number', [
        ':number' => $this->groupStatistics->flaggedContentStatics($group->id(), 'bookmark'),
      ]),
      '#suffix' => '</div>',
    ];

    $current_cache_tags = parent::getCacheTags();
    $build['#cache']['tags'] = Cache::mergeTags($current_cache_tags, ['group:' . $group->id()]);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    // The block is only available on group view page.
    $allowed_routes = [
      'entity.group.edit_form',
      'entity.group.canonical',
    ];

    if (in_array($this->routeMatch->getRouteName(), $allowed_routes)) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::neutral();
    }
  }

}
