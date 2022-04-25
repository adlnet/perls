<?php

namespace Drupal\perls_user\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides User Statistics block.
 *
 * @Block(
 *   id = "user_statistics_block",
 *   admin_label = @Translation("User Statistics")
 * )
 */
class UserStatisticsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new user stats block.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

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
      $container->get('current_route_match')
    );
  }

  /**
   * Renders view display blocks.
   */
  public function build() {

    // View: User Statistics - Recommendations.
    $build['user_recommendations_wrapper'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Recommendations'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['user-statics-recommendation'],
      ],
    ];

    $build['user_recommendations_wrapper']['number_of_recommendations'] = [
      '#type' => 'view',
      '#name' => 'user_statistics_recommendations',
      '#display_id' => 'number_of_recommendations',
    ];

    // View: User Statistics - Seen Items.
    $build['user_seen_wrapper'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Viewed'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['user-statics-seen'],
      ],
    ];

    $build['user_seen_wrapper']['number_of_items_seen_today'] = [
      '#type' => 'view',
      '#name' => 'user_statistics_seen_items',
      '#display_id' => 'number_of_items_seen_today',
    ];

    $build['user_seen_wrapper']['number_of_items_seen_this_week'] = [
      '#type' => 'view',
      '#name' => 'user_statistics_seen_items',
      '#display_id' => 'number_of_items_seen_this_week',
    ];

    $build['user_seen_wrapper']['number_of_items_seen_this_month'] = [
      '#type' => 'view',
      '#name' => 'user_statistics_seen_items',
      '#display_id' => 'number_of_items_seen_this_month',
    ];

    $build['user_seen_wrapper']['number_of_items_seen_all_time'] = [
      '#type' => 'view',
      '#name' => 'user_statistics_seen_items',
      '#display_id' => 'number_of_items_seen_all_time',
    ];

    // View: User Statistics - Completed Items.
    $build['user_completed_wrapper'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Completed'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['user-statics-completed'],
      ],
    ];

    $build['user_completed_wrapper']['number_of_items_completed_today'] = [
      '#type' => 'view',
      '#name' => 'user_statistics_completed_items',
      '#display_id' => 'number_of_items_completed_today',
    ];

    $build['user_completed_wrapper']['number_of_items_completed_this_week'] = [
      '#type' => 'view',
      '#name' => 'user_statistics_completed_items',
      '#display_id' => 'number_of_items_completed_this_week',
    ];

    $build['user_completed_wrapper']['number_of_items_completed_this_month'] = [
      '#type' => 'view',
      '#name' => 'user_statistics_completed_items',
      '#display_id' => 'number_of_items_completed_this_month',
    ];

    $build['user_completed_wrapper']['number_of_items_completed_all_time'] = [
      '#type' => 'view',
      '#name' => 'user_statistics_completed_items',
      '#display_id' => 'number_of_items_completed_all_time',
    ];

    // View: User Statistics - Bookmarked Items.
    $build['user_bookmarked_wrapper'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Bookmarked'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['user-statics-bookmarked'],
      ],
    ];

    $build['user_bookmarked_wrapper']['number_of_items_bookmarked_today'] = [
      '#type' => 'view',
      '#name' => 'user_statistics_bookmarked_items',
      '#display_id' => 'number_of_items_bookmarked_today',
    ];

    $build['user_bookmarked_wrapper']['number_of_items_bookmarked_this_week'] = [
      '#type' => 'view',
      '#name' => 'user_statistics_bookmarked_items',
      '#display_id' => 'number_of_items_bookmarked_this_week',
    ];

    $build['user_bookmarked_wrapper']['number_of_items_bookmarked_this_month'] = [
      '#type' => 'view',
      '#name' => 'user_statistics_bookmarked_items',
      '#display_id' => 'number_of_items_bookmarked_this_month',
    ];

    $build['user_bookmarked_wrapper']['number_of_items_bookmarked_all_time'] = [
      '#type' => 'view',
      '#name' => 'user_statistics_bookmarked_items',
      '#display_id' => 'number_of_items_bookmarked_all_time',
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    $user = $this->routeMatch->getParameter('user');

    if (!$user) {
      return AccessResult::forbidden();
    }

    // Ensure the current user has permission to view
    // the requested user account.
    return $user->access('view', $account, TRUE);
  }

}
