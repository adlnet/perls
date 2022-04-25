<?php

namespace Drupal\badges\Controller;

use Drupal\achievements\Entity\AchievementEntity;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\badges\Service\BadgeService;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller responses to flag and unflag action links.
 *
 * The response is a set of AJAX commands to update the
 * link in the page.
 */
class AwardBadgeController implements ContainerInjectionInterface {
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
   * Constructor.
   */
  public function __construct(BadgeService $badge_service, RendererInterface $renderer, MessengerInterface $messenger) {
    $this->badgeService = $badge_service;
    $this->renderer = $renderer;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('badges.badge_service'),
      $container->get('renderer'),
      $container->get('messenger')
    );
  }

  /**
   * Award a badge when called via a route.
   */
  public function award(AccountInterface $user, AchievementEntity $achievement) {
    $this->badgeService->awardBadge($user, $achievement->id());
    $message = t('@name badge has been assigned to @user.',
    [
      '@name' => $achievement->label(),
      '@user' => $user->getDisplayName(),
    ]
    );
    return $this->generateResponse($achievement, $user, $message);
  }

  /**
   * Revoke a badge when called via a route.
   */
  public function revoke(AccountInterface $user, AchievementEntity $achievement) {
    $this->badgeService->revokeBadge($user, $achievement->id());
    $message = t('@name badge has been revoked from @user.',
    [
      '@name' => $achievement->label(),
      '@user' => $user->getDisplayName(),
    ]
    );
    return $this->generateResponse($achievement, $user, $message);
  }

  /**
   * Reset saved data for a given user on  an achievement.
   */
  public function reset(AccountInterface $user, AchievementEntity $achievement) {
    $this->badgeService->setStoredData($achievement->getStorage(), [], $user->id());
    $message = t('Progress Storage for @name badge has been reset for @user.',
    [
      '@name' => $achievement->label(),
      '@user' => $user->getDisplayName(),
    ]
    );
    return $this->generateResponse($achievement, $user, $message);
  }

  /**
   * Generates a response after the badge has been updated.
   */
  private function generateResponse(AchievementEntity $achievement, UserInterface $user, $message) {
    $this->messenger->addMessage($message);
    $options['absolute'] = TRUE;
    $parameters['user'] = $user->id();
    $url = Url::fromRoute('achievements.achievements_controller_userAchievements', $parameters, $options);
    $response = new RedirectResponse($url->toString());
    return $response;
  }

}
