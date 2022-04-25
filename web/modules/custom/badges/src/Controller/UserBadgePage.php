<?php

namespace Drupal\badges\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\badges\Entity\AchievementEntity;
use Drupal\badges\Service\BadgeService;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller responses to flag and unflag action links.
 *
 * The response is a set of AJAX commands to update the
 * link in the page.
 */
class UserBadgePage implements ContainerInjectionInterface {
  /**
   * The flag service.
   *
   * @var \Drupal\badges\Service\BadgeService
   */
  protected $badgeService;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * The Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Class Resolver service.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * Constructor.
   */
  public function __construct(BadgeService $badge_service, RendererInterface $renderer, MessengerInterface $messenger, AccountInterface $user, EntityTypeManagerInterface $entity_type_manager, ClassResolverInterface $class_resolver) {
    $this->badgeService = $badge_service;
    $this->renderer = $renderer;
    $this->messenger = $messenger;
    $this->user = $user;
    $this->entityTypeManager = $entity_type_manager;
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('badges.badge_service'),
      $container->get('renderer'),
      $container->get('messenger'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('class_resolver'),
    );
  }

  /**
   * Create a page of badges for a particular user.
   */
  public function badges() {
    $user = $this->entityTypeManager->getStorage('user')->load($this->user->id());
    return $this->listAchievements($user, 'badge');
  }

  /**
   * Create a page of certificates for a particular user.
   */
  public function certificates() {
    $user = $this->entityTypeManager->getStorage('user')->load($this->user->id());
    return $this->listAchievements($user, 'certificate');
  }

  /**
   * Get a list of achievements of a given type for given user.
   */
  protected function listAchievements(UserInterface $user, $type = 'badge') {
    $unlocks = achievements_unlocked_already(NULL, $user->id());
    $achievers = achievements_totals_user($user->id());
    $achiever = reset($achievers);
    $achievements = achievements_load_all();

    $build['#theme_wrappers'] = ['container'];
    $build['#attributes'] = [
      'class' => [
        'achievements',
        'achievements-' . $type,
      ],
    ];
    $build['#attached'] = [
      'library' => [
        'achievements/achievements',
      ],
    ];
    $cache_tags = $this->entityTypeManager->getDefinition('achievement_entity')->getListCacheTags();
    foreach ($achievements as $achievement_id => $achievement) {
      if (!empty($achievement->isInvisible()) && !isset($unlocks[$achievement_id]) ||
      $achievement->getType() === 'certificate' && !isset($unlocks[$achievement_id])) {
        // Invisibles only display if this $account has unlocked them.
        continue;
      }
      if ($achievement->getType() !== $type) {
        continue;
      }
      $cache_tags = Cache::mergeTags($cache_tags, $achievement->getCacheTags());
      // If it's not an invisible achievement, we've got to show something.
      // $build out what and where.
      $build['achievements'][$achievement_id]['#achievement_entity'] = $achievement;
      $build['achievements'][$achievement_id]['#user_id'] = $user->id();
      $build['achievements'][$achievement_id]['#theme'] = 'achievement';

      if (isset($unlocks[$achievement_id])) {
        $build['achievements'][$achievement_id]['#unlock'] = $unlocks[$achievement_id];
        // By setting the negative weight to the timestamp,
        // the latest unlocks are always shown at the top.
        $build['achievements'][$achievement_id]['#weight'] = -$unlocks[$achievement_id]['timestamp'];
      }
      // Locked.
      else {
        $build['achievements'][$achievement_id]['#weight'] = 1;
      }
    }
    if (!isset($build['achievements']) || empty($build['achievements'])) {
      $build['#markup'] = '<div class="empty_message">You have not earned any ' . $type . 's yet</div>';
    }

    $build['#cache'] = [
      'context' => ['user'],
      'tags' => $cache_tags,
    ];

    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $build;

  }

  /**
   * A function to return a specific certificate view.
   */
  public function viewCertificate(AccountInterface $user, AchievementEntity $achievement) {
    $build['cert'] = [
      '#theme' => 'achievement_modalview',
      '#achievement' => $achievement,
      '#user' => $user,
    ];

    $build['#cache'] = [
      'context' => Cache::mergeContexts(['user'], $achievement->getCacheContexts()),
      'tags' => $achievement->getCacheTags(),
    ];
    return $build;
  }

  /**
   * Verifies the user can access the detailed achievement view.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessAchievement(AccountInterface $account, RouteMatchInterface $route_match) {
    $user = $route_match->getParameter('user');

    return AccessResult::allowedIfHasPermission($account, 'administer achievements')
      ->orIf(AccessResult::allowedIf($account->id() === $user->id())->cachePerUser());
  }

}
