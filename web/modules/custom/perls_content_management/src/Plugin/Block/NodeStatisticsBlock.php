<?php

namespace Drupal\perls_content_management\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\perls_content_management\NodeStatistics;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Node Statistics block.
 *
 * @Block(
 *   id = "node_statistics_block",
 *   admin_label = @Translation("Node Statistics")
 * )
 */
class NodeStatisticsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The node statistics service.
   *
   * @var \Drupal\perls_content_management\NodeStatistics
   */
  private $nodeStatistics;

  /**
   * Route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  private $routeMatch;

  /**
   * Provides a node statistics block for individual nodes.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\perls_content_management\NodeStatistics $node_statistics
   *   A helper service to collect the node statistics.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match helper.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    NodeStatistics $node_statistics,
    RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->nodeStatistics = $node_statistics;
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
      $container->get('perls_content_management.node_statistics'),
      $container->get('current_route_match')
    );
  }

  /**
   * Renders view display blocks.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node');
    $build['#cache'] = [
      'contexts' => [
        'route',
      ],
    ];

    if (!$node instanceof NodeInterface) {
      return $build;
    }

    // View: Node Statistics - Number of Recommendations.
    $build['node_statistics_recommendations_wrapper'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Recommended to'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['node-statistics-recommendation'],
      ],
    ];

    $build['node_statistics_recommendations_wrapper']['recommendations_number'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t(':number users', [
        ':number' => $this->nodeStatistics->flaggedContentStatistics($node->id(), 'recommendation'),
      ]),
      '#suffix' => '</div>',
    ];

    $build['node_statistics_seen_wrapper'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Viewed'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['node-statistics-seen'],
      ],
    ];

    // View: Node Statistics - Today seen items.
    $build['node_statistics_seen_wrapper']['items_seen_today'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Today: :number', [
        ':number' => $this->nodeStatistics->seenContentStatistics($node->id(), 'today'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Node Statistics - Weekly seen items.
    $build['node_statistics_seen_wrapper']['items_seen_week'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Week: :number', [
        ':number' => $this->nodeStatistics->seenContentStatistics($node->id(), 'week'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Node Statistics - Monthly seen items.
    $build['node_statistics_seen_wrapper']['items_seen_month'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Month: :number', [
        ':number' => $this->nodeStatistics->seenContentStatistics($node->id(), 'month'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Node Statistics - Seen all items.
    $build['node_statistics_seen_wrapper']['items_seen_all'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Year: :number', [
        ':number' => $this->nodeStatistics->seenContentStatistics($node->id(), 'year'),
      ]),
      '#suffix' => '</div>',
    ];

    $build['node_statistics_completed_wrapper'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Completed'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['node-statistics-completed'],
      ],
    ];

    // View: Node Statistics - Completed content today.
    $build['node_statistics_completed_wrapper']['items_completed_today'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Today: :number', [
        ':number' => $this->nodeStatistics->flaggedContentStatistics($node->id(), 'completed', 'today'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Node Statistics - Completed content in this week.
    $build['node_statistics_completed_wrapper']['items_completed_week'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Week: :number', [
        ':number' => $this->nodeStatistics->flaggedContentStatistics($node->id(), 'completed', 'week'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Node Statistics - Completed content in this month.
    $build['node_statistics_completed_wrapper']['items_completed_month'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Month: :number', [
        ':number' => $this->nodeStatistics->flaggedContentStatistics($node->id(), 'completed', 'month'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Node Statistics - All Completed content.
    $build['node_statistics_completed_wrapper']['items_completed_all'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Total: :number', [
        ':number' => $this->nodeStatistics->flaggedContentStatistics($node->id(), 'completed'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Node Statistics - Bookmarked content today.
    $build['node_statistics_bookmarked_wrapper'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Bookmarked'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['node-statistics-bookmarked'],
      ],
    ];

    $build['node_statistics_bookmarked_wrapper']['items_bookmarked_today'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Today: :number', [
        ':number' => $this->nodeStatistics->flaggedContentStatistics($node->id(), 'bookmark', 'today'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Node Statistics - Bookmarked content in this week.
    $build['node_statistics_bookmarked_wrapper']['items_bookmarked_week'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Week: :number', [
        ':number' => $this->nodeStatistics->flaggedContentStatistics($node->id(), 'bookmark', 'week'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Node Statistics - Bookmarked content in this month.
    $build['node_statistics_bookmarked_wrapper']['items_bookmarked_month'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Month: :number', [
        ':number' => $this->nodeStatistics->flaggedContentStatistics($node->id(), 'bookmark', 'month'),
      ]),
      '#suffix' => '</div>',
    ];

    // View: Node Statistics - All bookmarked content.
    $build['node_statistics_bookmarked_wrapper']['items_bookmarked_all'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Total: :number', [
        ':number' => $this->nodeStatistics->flaggedContentStatistics($node->id(), 'bookmark'),
      ]),
      '#suffix' => '</div>',
    ];

    $build['node_statistics_feedback_average_wrapper'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Learner Feedback'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['node-statistics-feedback_average'],
      ],
    ];

    // Feedback: Node Statistics - Today feedback submitted.
    $build['node_statistics_feedback_average_wrapper']['items_feedback_average'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t(':number recommend', [
        ':number' => $this->nodeStatistics->webformSubmissionAverageStatistics($node->id(), 'all'),
      ]),
      '#suffix' => '</div>',
    ];

    $build['node_statistics_feedback_wrapper'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Feedback Submissions'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['node-statistics-feedback'],
      ],
    ];

    // Feedback: Node Statistics - Today feedback submitted.
    $build['node_statistics_feedback_wrapper']['items_feedback_today'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Today: :number', [
        ':number' => $this->nodeStatistics->webformSubmissionCountStatistics($node->id(), 'content_specific_webform', 'today'),
      ]),
      '#suffix' => '</div>',
    ];

    // Feedback: Node Statistics - Week feedback submitted.
    $build['node_statistics_feedback_wrapper']['items_feedback_week'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Week: :number', [
        ':number' => $this->nodeStatistics->webformSubmissionCountStatistics($node->id(), 'content_specific_webform', 'week'),
      ]),
      '#suffix' => '</div>',
    ];

    // Feedback: Node Statistics - Month feedback submitted.
    $build['node_statistics_feedback_wrapper']['items_feedback_month'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Month: :number', [
        ':number' => $this->nodeStatistics->webformSubmissionCountStatistics($node->id(), 'content_specific_webform', 'month'),
      ]),
      '#suffix' => '</div>',
    ];

    // Feedback: Node Statistics - Year feedback submitted.
    $build['node_statistics_feedback_wrapper']['items_feedback_year'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t('Year: :number', [
        ':number' => $this->nodeStatistics->webformSubmissionCountStatistics($node->id(), 'content_specific_webform', 'year'),
      ]),
      '#suffix' => '</div>',
    ];

    // Get comment settings for this node.
    $comments = [];
    if ($node->hasField('field_comments') && !empty($node->get('field_comments'))) {
      $comments = $node->get('field_comments')->first()->getValue();
    }

    // Proceed with comments stats if node comments are open.
    if (isset($comments['status']) && $comments['status'] == '2') {
      $build['node_statistics_comment_wrapper'] = [
        '#type' => 'fieldgroup',
        '#title' => $this->t('Comments Posted'),
        '#open' => TRUE,
        '#attributes' => [
          'class' => ['node-statistics-comments'],
        ],
      ];

      // Comment: Node Statistics - Today comments submitted.
      $build['node_statistics_comment_wrapper']['items_comment_today'] = [
        '#prefix' => '<div>',
        '#markup' => $this->t('Today: :number', [
          ':number' => $this->nodeStatistics->commentCountStatistics($node->id(), 'public_discussion', 'today'),
        ]),
        '#suffix' => '</div>',
      ];

      // Comment: Node Statistics - Week comments submitted.
      $build['node_statistics_comment_wrapper']['items_comment_week'] = [
        '#prefix' => '<div>',
        '#markup' => $this->t('Week: :number', [
          ':number' => $this->nodeStatistics->commentCountStatistics($node->id(), 'public_discussion', 'week'),
        ]),
        '#suffix' => '</div>',
      ];

      // Comment: Node Statistics - Month comments submitted.
      $build['node_statistics_comment_wrapper']['items_comment_month'] = [
        '#prefix' => '<div>',
        '#markup' => $this->t('Month: :number', [
          ':number' => $this->nodeStatistics->commentCountStatistics($node->id(), 'public_discussion', 'month'),
        ]),
        '#suffix' => '</div>',
      ];

      // Comment: Node Statistics - Year comments submitted.
      $build['node_statistics_comment_wrapper']['items_comment_year'] = [
        '#prefix' => '<div>',
        '#markup' => $this->t('Year: :number', [
          ':number' => $this->nodeStatistics->commentCountStatistics($node->id(), 'public_discussion', 'year'),
        ]),
        '#suffix' => '</div>',
      ];
    }

    $current_cache_tags = parent::getCacheTags();
    $build['#cache']['tags'] = Cache::mergeTags($current_cache_tags,
      [
        'node:' . $node->id(),
        'flagging_list',
        'comment_list',
        'webform_submission_list',
      ]);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    // The block is only available on node edit page.
    $allowed_routes = [
      'entity.node.edit_form',
    ];

    if (in_array($this->routeMatch->getRouteName(), $allowed_routes)) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::neutral();
    }
  }

}
