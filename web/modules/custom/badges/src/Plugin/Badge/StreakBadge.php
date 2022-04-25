<?php

namespace Drupal\badges\Plugin\Badge;

use Drupal\achievements\Entity\AchievementEntity;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\badges\BadgePluginBase;
use Drupal\badges\Service\BadgeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Streak Badge Plugin.
 *
 * @Badge(
 *   id = "streak_badge_plugin",
 *   label = @Translation("Log in Streak"),
 *   description = @Translation("Daily Streak - This badge type is unlocked by using the app for a given number of days.")
 * )
 */
class StreakBadge extends BadgePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The badge Service.
   *
   * @var \Drupal\badges\Service\BadgeService
   */
  protected $badgeService;

  /**
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('Badge Plugin'),
      $container->get('badges.badge_service')
    );
  }

  /**
   * Constructor for Assign Badge Action.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerInterface $logger,
    BadgeService $badge_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->badgeService = $badge_service;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(AchievementEntity $achievement = NULL) {
    $previous_value = '';
    if ($achievement !== NULL &&  $achievement->getThirdPartySetting('badges', $this->getPluginId())) {
      $previous_value = $achievement->getThirdPartySetting('badges', $this->getPluginId())['days'];
    }
    return [
      '#type' => 'textfield',
      '#title' => $this->t('Number of Days'),
      '#description' => $this->t('The number of consecutive days the user must login to get badge.'),
      '#default_value' => $previous_value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function updateConfigWithBadgeSettings(FormStateInterface $form_state, AchievementEntity $achievement) {
    $data = [
      'days' => $form_state->getValue($this->getPluginId()),
    ];
    $achievement->setThirdPartySetting('badges', $this->getPluginId(), $data);
    $achievement->set('storage', $this->getStorageName());
  }

  /**
   * {@inheritdoc}
   */
  public function removeBadgeSettingsFromConfig(AchievementEntity $achievement) {
    parent::removeBadgeSettingsFromConfig($achievement);
    if ($achievement->get('storage') === $this->getStorageName()) {
      $achievement->set('storage', NULL);
    }
  }

  /**
   * Get the name of the storage shared among all daily streak plugins.
   */
  public function getStorageName() {
    return $this->pluginId . '_daily_streak';
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage(AchievementEntity $achievement) {
    // This badge shares storage between all achievements of this type.
    return $this->getStorageName();
  }

  /**
   * {@inheritdoc}
   */
  public function updateUserProgress(AccountInterface $user, array $updates = NULL) {
    // Award a badge if the user has logged into the app on the required
    // number of days.
    if (!isset($updates['time'])) {
      return;
    }
    $new_update = new DrupalDateTime($updates['time']->format('Y/m/d'));

    $data = $this->badgeService->getStoredData($this->getStorageName(), $user->id());
    if (!$data || empty($data)) {
      // Give data an initial value.
      $data['current_streak'] = 0;
      $data['longest_streak'] = 0;
      $data['last_update'] = $new_update->format('Y/m/d');
      $this->badgeService->setStoredData($this->getStorageName(), $data, $user->id());
      return;
    }
    $current_streak = $data['current_streak'];
    $last_update = new DrupalDateTime($data['last_update']);
    $interval = $last_update->diff($new_update);
    // If we missed days or get a date in past reset streak counter.
    if ($interval->days > 1 || $interval->invert == 1) {
      // Streak broken, reset.
      $data['current_streak'] = 0;
      $data['last_update'] = $new_update->format('Y/m/d');
      $this->badgeService->setStoredData($this->getStorageName(), $data, $user->id());
      return;
    }
    elseif ($interval->days == 1) {
      $current_streak = $current_streak + 1;
      $data['current_streak'] = $current_streak;
      $data['last_update'] = $new_update->format('Y/m/d');
      if ($current_streak > $data['longest_streak']) {
        $data['longest_streak'] = $current_streak;
      }
      $this->badgeService->setStoredData($this->getStorageName(), $data, $user->id());
    }
    else {
      // Either same day or error so do nothing.
      return;
    }

    // Now we need to check to see if we need to award new badge.
    $badges = $this->badgeService->getBadgesByType($this->getPluginId());

    foreach ($badges as $badge_id => $badge) {
      // Get the streak value need to award badge.
      $days = intval($badge->getThirdPartySetting('badges', $this->getPluginId())['days']);
      if ($current_streak >= $days) {
        $this->badgeService->awardBadge($user, $badge_id);
      }
    }
  }

}
