<?php

namespace Drupal\badges\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheTagsInvalidator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\badges\BadgePluginManager;
use Drupal\badges\ExtendedAchievementInterface;
use Drupal\user\Entity\User;

/**
 * Service for granting and updating badges.
 */
class BadgeService {
  use StringTranslationTrait;
  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The Badge plugin manager.
   *
   * @var \Drupal\badges\BadgePluginManager
   */
  protected $badgePluginManager;

  /**
   * The Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Cache tag invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidator
   */
  protected $cacheTagInvalidator;

  /**
   * The Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a FirebaseServiceBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $loggerChannel
   *   The logger channel.
   * @param \Drupal\badges\BadgePluginManager $badgePluginManager
   *   The plugin manager for badges.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter used in this plugin.
   * @param \Drupal\Core\Cache\CacheTagsInvalidator $cache_tag_invalidator
   *   Cache invalidator service.
   * @param \Drupal\core\Database\Connection $connection
   *   Database connection.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    LoggerChannelInterface $loggerChannel,
    BadgePluginManager $badgePluginManager,
    DateFormatterInterface $dateFormatter,
    CacheTagsInvalidator $cache_tag_invalidator,
    Connection $connection
  ) {
    $this->configFactory = $configFactory;
    $this->logger = $loggerChannel;
    $this->badgePluginManager = $badgePluginManager;
    $this->dateFormatter = $dateFormatter;
    $this->cacheTagInvalidator = $cache_tag_invalidator;
    $this->connection = $connection;
  }

  /**
   * Get an array of badges already unlocked by user.
   */
  public function getUnlockedBadges(AccountInterface $user, $achievement_id = NULL) {
    return achievements_unlocked_already($achievement_id, $user->id());
  }

  /**
   * A method to return the achievement entities of all badges in the system.
   */
  public function getAllBadges() {
    return achievements_load_all();
  }

  /**
   * A method to get an achievement by id.
   */
  public function getAchievementById($id) {
    $achievements = $this->getAllBadges();
    if (isset($achievements[$id])) {
      return $achievements[$id];
    }
    else {
      return NULL;
    }

  }

  /**
   * A method to return the list of earned certificates.
   */
  public function listAchievements(AccountInterface $user, $include_invisible = FALSE, $type = NULL, $include_locked = TRUE) {
    $achievements = achievements_load_all();
    $unlocks = achievements_unlocked_already(NULL, $user->id());
    $achievement_list = [];
    foreach ($achievements as $achievement_id => $achievement) {
      $test = $achievement->access('view', $user);
      if (($type !== NULL && $type !== $achievement->getType()) ||
        (!isset($unlocks[$achievement_id]) && $include_locked === FALSE) ||
        $achievement->access('view', $user) === FALSE
      ) {
        continue;
      }
      $achievement_list[$achievement_id] = $achievement->getInfo($user);
      if (isset($unlocks[$achievement_id])) {
        $achievement_list[$achievement_id]['unlocked_timestamp'] = $this->dateFormatter->format($unlocks[$achievement_id]['timestamp'], 'html_datetime');
      }
    }
    return $achievement_list;

  }

  /**
   * A method to return the full list of available badges.
   *
   * This method get a list of every badge available in the system.
   * It returns them as a badge id indexed array.
   */
  public function listBadges(AccountInterface $user, $include_invisible = FALSE) {
    $unlocks = achievements_unlocked_already(NULL, $user->id());
    $achievements = achievements_load_all();
    $badges = [];
    foreach ($achievements as $achievement_id => $achievement) {
      if (
      !$include_invisible
      && (
        !empty($achievement->isInvisible())
        && !isset($unlocks[$achievement_id])
      )
      ) {
        // We include invisible badges if the override is set or
        // the user has already unlocked them.
        continue;
      }
      // Load the plugin for this badge.
      $plugin = $this->getBadgePlugin($achievement->getThirdPartySetting('badges', 'plugin_id'));
      if ($plugin->getAchievementType() !== 'badge') {
        continue;
      }
      // Here we choose what information we want to return.
      $badges[$achievement_id] =
      [
        'id' => $achievement->id(),
        'uuid' => $achievement->uuid(),
        'label' => $achievement->label(),
        'description' => $achievement->getDescription(),
        'badge_type' => $achievement->getThirdPartySetting('badges', 'badge_type'),
        'secret' => $achievement->isSecret(),
        'invisible' => $achievement->isInvisible(),
        'locked_image_url' => file_create_url($achievement->getImagePath('locked')),
        'unlocked_image_url' => file_create_url($achievement->getImagePath('unlocked')),
        'unlocked' => 0,
        'unlocked_timestamp' => NULL,
        'status_description' => $this->t('Achievement Locked'),
      ];
      if (isset($unlocks[$achievement_id])) {
        $badges[$achievement_id]['unlocked'] = 1;
        $badges[$achievement_id]['status_description'] = $this->t('Awarded');
        $badges[$achievement_id]['unlocked_timestamp'] = $this->dateFormatter->format($unlocks[$achievement_id]['timestamp'], 'html_datetime');
      }
    }
    return $badges;
  }

  /**
   * Get a list of Badge Types available in the system.
   */
  public function listBadgeTypes() {
    $badge_types = [];
    foreach (achievements_load_all() as $badge_id => $badge_entity) {
      $badge_types[$badge_id] = $badge_entity->label();
    }
    return $badge_types;
  }

  /**
   * Assign a badge to a user.
   */
  public function awardBadge(AccountInterface $user, $badge_id, $timestamp = NULL) {
    if (!isset($this->listBadgeTypes()[$badge_id])) {
      // Badge id doesn't exist.
      return FALSE;
    }
    achievements_unlocked($badge_id, $user->id(), $timestamp);

    // Drop user cache tags, otherwise the awarded badge won't appear after page
    // reload.
    $this->invalidateUserTags($user);

    return TRUE;
  }

  /**
   * Remove a badge from a user.
   */
  public function revokeBadge(AccountInterface $user, $badge_id) {
    if (!isset($this->listBadgeTypes()[$badge_id])) {
      // Badge id doesn't exist.
      return FALSE;
    }
    achievements_locked($badge_id, $user->id());
    // Drop user cache tags, otherwise the revoked badge won't disappear after
    // page reload.
    $this->invalidateUserTags($user);
    return TRUE;
  }

  /**
   * Get a list of options for badge plugin.
   */
  public function getBadgePluginOptions() {
    $options = [
      'manual' => 'Manual',
    ];
    $disabled_plugins = $this->listDisabledPlugins();
    $plugin_definitions = $this->badgePluginManager->getDefinitions();
    foreach ($plugin_definitions as $id => $definition) {
      if (in_array($id, $disabled_plugins)) {
        continue;
      }
      $options[$id] = $definition['label'];
    }
    return $options;
  }

  /**
   * Get an array of active plugins.
   */
  public function getBadgePlugins($include_disabled = FALSE) {
    $badge_engines = [];
    $plugin_definitions = $this->badgePluginManager->getDefinitions();
    $disabled_plugins = $this->listDisabledPlugins();
    foreach ($plugin_definitions as $id => $definition) {
      if (!$include_disabled && in_array($id, $disabled_plugins)) {
        continue;
      }
      // Load a version of the recommendation engine plugin.
      try {
        /** @var \Drupal\badges\BadgePluginInterface $badge */
        $badge = $this->badgePluginManager->createInstance($id);
      }
      catch (PluginException $e) {
        continue;
      }
      $badge_engines[$id] = $badge;
    }
    return $badge_engines;
  }

  /**
   * Get a list of enabled plugins.
   */
  protected function listDisabledPlugins() {
    $disabled_plugins = $this->configFactory->get('achievements.settings')->get('disabled_plugins');
    return $disabled_plugins ?: [];
  }

  /**
   * Get a particular badge plugin by id.
   */
  public function getBadgePlugin($plugin_id) {
    if ($this->badgePluginManager->getDefinition($plugin_id, FALSE)) {
      return $this->badgePluginManager->createInstance($plugin_id);
    }
    else {
      return NULL;
    }
  }

  /**
   * Get a list of badges associated with a given Badge Plugin Id.
   */
  public function getBadgesByType($plugin_id) {
    $achievements = achievements_load_all();
    $badges = [];
    foreach ($achievements as $achievement_id => $achievement) {
      if ($achievement->getThirdPartySetting('badges', 'plugin_id') === $plugin_id) {
        $badges[$achievement_id] = $achievement;
      }
    }
    return $badges;
  }

  /**
   * Get link to award or revoke a badge.
   */
  public function getAwardRevokeLink($achievement_id, $user_id, $state) {
    $link = [
      'title' => ($state === 'locked') ? 'Award' : 'Revoke',
    ];
    $parameters = [
      'user' => $user_id,
      'achievement' => $achievement_id,
    ];
    if ($state === 'locked') {
      // Need award link.
      $url = Url::fromRoute('badges.award_badge', $parameters);
    }
    else {
      // Need revoke link.
      $url = Url::fromRoute('badges.revoke_badge', $parameters);
    }
    $link['url'] = $url;
    return $link;
  }

  /**
   * Get link to award or revoke a badge.
   */
  public function getResetLink($achievement_id, $user_id) {
    $link = [
      'title' => 'Reset Progress',
    ];
    $parameters = [
      'user' => $user_id,
      'achievement' => $achievement_id,
    ];
    $url = Url::fromRoute('badges.reset_badge', $parameters);
    $link['url'] = $url;
    return $link;
  }

  /**
   * Reset stored data for user.
   */
  public function resetUserStatus(AccountInterface $user) {
    if (!$user) {
      return;
    }
    $achievements = $this->getAllBadges();
    foreach ($achievements as $achievement) {
      $this->revokeBadge($user, $achievement->id());
      if ($data = $this->getStoredData($achievement->getStorage(), $user->id())) {
        $this->setStoredData($achievement->getStorage(), [], $user->id());
      }
    }
  }

  /**
   * Get the storage associated with a badge.
   *
   * Some badge types share this storage area. Other badges use the
   * badge id to find associated storage.
   */
  public function getStoredData($storage_id, $user_id) {
    return achievements_storage_get($storage_id, $user_id);
  }

  /**
   * Set the storage associated with a badge.
   *
   * Some badge types share this storage area. Other badges use the
   * badge id to find associated storage.
   */
  public function setStoredData($storage_id, $data, $user_id) {
    achievements_storage_set($storage_id, $data, $user_id);
  }

  /**
   * Get a list of acceptable types for achievements.
   */
  public function getAchievementTypeOptions() {
    return [
      'badge' => t('Badge'),
      'certificate' => t('Certificate'),
    ];
  }

  /**
   * Invalidate user's cache tags.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A drupal account.
   */
  public function invalidateUserTags(AccountInterface $account) {
    /** @var \Drupal\user\Entity\User $user */
    $user = User::load($account->id());
    $tags = $user->getCacheTags();
    $this->cacheTagInvalidator->invalidateTags($tags);
  }

  /**
   * Get the achievement page associated with a achievement.
   */
  public function getAchievementPageUrl(ExtendedAchievementInterface $achievement) {
    $options = [
      'fragment' => $achievement->id(),
    ];
    if ($achievement->getType() === 'certificate') {
      return Url::fromRoute('badges.user_certificates', [], $options);
    }
    else {
      return Url::fromRoute('badges.user_badges', [], $options);
    }
  }

  /**
   * Delete all information associated with a badge.
   */
  public function deleteAchievement(ExtendedAchievementInterface $achievement) {
    // Delete unlock information.
    $this->connection->delete('achievement_unlocks')
      ->condition('achievement_id', $achievement->id())
      ->execute();
    // Delete totals information.
    $this->connection->delete('achievement_totals')
      ->condition('achievement_id', $achievement->id())
      ->execute();
    $achievement->delete();
  }

  /**
   * Mark some or all achievements as seen for a given user.
   */
  public function markAchievementsAsSeen($user_id, $achievement_ids = NULL) {
    $query = $this->connection->update('achievement_unlocks')
      ->fields(['seen' => 1])
      ->condition('uid', $user_id)
      ->condition('seen', 0);
    if ($achievement_ids) {
      if (!is_array($achievement_ids)) {
        $achievement_ids = [$achievement_ids];
      }
      $query->condition('achievement_id', $achievement_ids, 'IN');
    }
    $query->execute();
  }

  /**
   * Clean up the achievement_unlocks table.
   */
  public function cleanUnlockedData() {
    $all_achievements_ids = array_keys($this->listBadgeTypes());
    $this->connection->delete('achievement_unlocks')
      ->condition('achievement_id', $all_achievements_ids, 'NOT IN')
      ->execute();
  }

}
