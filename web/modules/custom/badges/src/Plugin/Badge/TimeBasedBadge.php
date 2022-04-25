<?php

namespace Drupal\badges\Plugin\Badge;

use Drupal\achievements\Entity\AchievementEntity;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\badges\BadgePluginBase;
use Drupal\badges\Service\BadgeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Time-Based Badge Plugin.
 *
 * @Badge(
 *   id = "time_based_badge_plugin",
 *   label = @Translation("Time Attack"),
 *   description = @Translation("Time In App - Badge that gets unlocked by using the app for a set amount of time.")
 * )
 */
class TimeBasedBadge extends BadgePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The badge Service.
   *
   * @var \Drupal\badges\Service\BadgeService
   */
  protected $badgeService;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('Badge Plugin'),
      $container->get('badges.badge_service'),
      $container->get('config.factory')->get('achievements.settings')
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
    BadgeService $badge_service,
    ImmutableConfig $config
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->badgeService = $badge_service;
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(AchievementEntity $achievement = NULL) {
    $previous_value = '';
    if ($achievement !== NULL &&  $achievement->getThirdPartySetting('badges', $this->getPluginId())) {
      $previous_value = $achievement->getThirdPartySetting('badges', $this->getPluginId())['minutes'];
    }
    return [
      '#type' => 'textfield',
      '#title' => $this->t('Number of minutes'),
      '#description' => $this->t('The number of consecutive minutes the user must use the app to get badge.'),
      '#default_value' => $previous_value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function updateConfigWithBadgeSettings(FormStateInterface $form_state, AchievementEntity $achievement) {
    $data = [
      'minutes' => $form_state->getValue($this->getPluginId()),
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
    return 'time_based_badge';
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
    // Award a badge if the user has used the app for a period of time.
    if (!isset($updates['time']) || !isset($updates['verb'])) {
      return;
    }

    $new_update = $updates['time']->getTimeStamp();

    $data = $this->badgeService->getStoredData($this->getStorageName(), $user->id());
    if (!$data || empty($data)) {
      // Give data an initial value.
      $data['time_in_app'] = 0;
      $data['session_started'] = 0;
      $data['last_session_update'] = $new_update;
    }

    $last_update = $data['last_session_update'];

    // If new update is before the last recorded update we return to avoid
    // counting sessions twice.
    if ($last_update > $new_update) {
      return;
    }

    // Get the session timeout from config.
    $timeout = $this->config->get('xapi_session_timeout') ?: '5';
    // Timeout in seconds.
    $timeout = intval($timeout) * 60;

    if ($updates['verb'] == 'opened' || $data['session_started'] === 0 || ($new_update - $last_update) > $timeout) {
      // Start a new session.
      $data['session_started'] = 1;
      $data['last_session_update'] = $new_update;
    }
    else {
      if ($data['session_started'] == 1) {
        $data['time_in_app'] = $data['time_in_app'] + (($new_update - $last_update));
        $data['last_session_update'] = $new_update;
        if ($updates['verb'] == 'closed') {
          $data['session_started'] = 0;
        }
      }
      else {
        // No update so return.
        return;
      }
    }
    // Save progress.
    $this->badgeService->setStoredData($this->getStorageName(), $data, $user->id());

    // Now we need to check to see if we need to award new badge.
    $badges = $this->badgeService->getBadgesByType($this->getPluginId());
    $time_in_app = $data['time_in_app'] / 60;

    foreach ($badges as $badge_id => $badge) {
      // Get the streak value need to award badge.
      $minutes = intval($badge->getThirdPartySetting('badges', $this->getPluginId())['minutes']);
      if ($time_in_app >= $minutes) {
        $this->badgeService->awardBadge($user, $badge_id);
      }
    }
  }

}
