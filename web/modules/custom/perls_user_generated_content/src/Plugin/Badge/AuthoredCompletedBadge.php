<?php

namespace Drupal\perls_user_generated_content\Plugin\Badge;

use Drupal\achievements\Entity\AchievementEntity;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\badges\BadgePluginBase;
use Drupal\badges\Service\BadgeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Badge for having other learners complete your content.
 *
 * @Badge(
 *   id = "authored_count_completed_badge_plugin",
 *   label = @Translation("Authored completions"),
 *   description = @Translation("Awarded to an author when users complete their content."),
 * )
 */
class AuthoredCompletedBadge extends BadgePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The badge Service.
   *
   * @var \Drupal\badges\Service\BadgeService
   */
  protected $badgeService;

  /**
   * The flag Service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The entity type manager Service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
      $container->get('flag'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Constructor for Authored Completed Badge.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerInterface $logger,
    BadgeService $badge_service,
    FlagServiceInterface $flag_service,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->badgeService = $badge_service;
    $this->flagService = $flag_service;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(AchievementEntity $achievement = NULL) {
    $count = 1;
    $selected_bundles = [];
    if ($achievement !== NULL &&  $achievement->getThirdPartySetting('badges', $this->getPluginId())) {
      $count = $this->getStoredValue($achievement, 'required_count');
      $selected_bundles = $this->getStoredValue($achievement, 'selected_bundles');
    }
    $return_array = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $return_array['required_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Count'),
      '#description' => $this->t('The number items needed to earn this badge.'),
      '#default_value' => $count,
      '#min' => 1,
    ];
    $return_array['selected_bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select content types that should be counted.'),
      '#options' => $this->getAllowedEntityBundles(),
      '#default_value' => $selected_bundles,
    ];
    return $return_array;
  }

  /**
   * {@inheritdoc}
   */
  public function updateConfigWithBadgeSettings(FormStateInterface $form_state, AchievementEntity $achievement) {
    $achievement->setThirdPartySetting('badges', $this->getPluginId(), $form_state->getValue($this->getPluginId()));
  }

  /**
   * {@inheritdoc}
   */
  public function updateUserProgress(AccountInterface $user, array $updates = NULL) {
    // Award badge if a user has flagged enough content.
    $badges = $this->badgeService->getBadgesByType($this->getPluginId());

    $unlocked_badges = $this->badgeService->getUnlockedBadges($user);

    // Roles to exclude we only want to count learners.
    $uids = $this->entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->condition('roles', NULL, 'IS NULL')
      ->condition('uid', $user->id(), '!=')
      ->condition('status', 1)
      ->execute();
    // If no learners in the system we can stop here.
    if (empty($uids)) {
      return;
    }
    // For load each badge that users this plugin.
    foreach ($badges as $badge_id => $badge) {
      // We want to get a count on the number of
      // If already unlocked continue.
      if (isset($unlocked_badges[$badge_id])) {
        continue;
      }

      // If required count is not set just skip badge.
      $required_count = $this->getStoredValue($badge, 'required_count');
      if (!$required_count || $required_count === 0) {
        continue;
      }

      // Get a list of all the content the author has published.
      $ids = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery()
        ->condition('uid', $user->id())
        ->condition('status', 1)
        ->condition('type', $this->getSelectedBundles($badge), 'IN')
        ->execute();
      if (empty($ids)) {
        continue;
      }

      // We have a list of authored content now check completions.
      $items = $this->entityTypeManager
        ->getStorage('flagging')
        ->getQuery()
        ->condition('uid', $uids, 'IN')
        ->condition('flag_id', 'completed')
        ->condition('entity_type', 'node')
        ->condition('entity_id', $ids, 'IN')
        ->execute();
      // If all entities are flagged award badge.
      if (count($items) >= $required_count) {
        $this->badgeService->awardBadge($user, $badge_id);
      }

    }
  }

  /**
   * Get the flag object this badge is used with.
   */
  protected function getFlag() {
    return $this->flagService->getFlagById('completed');
  }

  /**
   * Return the entity type this flag is used to on.
   */
  protected function getEntityType() {
    $flag = $this->getFlag();
    return $flag->getFlaggableEntityTypeId();
  }

  /**
   * Return the entity bundles this flag is used to on.
   */
  protected function getAllowedEntityBundles() {
    $flag = $this->getFlag();
    $bundles = $flag->getBundles();
    $return_array = [];
    foreach ($bundles as $bundle) {
      $return_array[$bundle] = $bundle;
    }
    return $return_array;
  }

  /**
   * Get an entity query friendly version of the selected entity types.
   */
  protected function getSelectedBundles(AchievementEntity $achievement) {
    $selected_bundles = $this->getStoredValue($achievement, 'selected_bundles');
    $bundles = [];
    if (!$selected_bundles) {
      return $bundles;
    }
    foreach ($selected_bundles as $id => $key) {
      if ($key !== 0) {
        $bundles[] = $id;
      }
    }
    return $bundles;
  }

  /**
   * Get a value from third party settings.
   */
  protected function getStoredValue(AchievementEntity $achievement, $key) {
    return $achievement->getThirdPartySetting('badges', $this->getPluginId())[$key];
  }

}
