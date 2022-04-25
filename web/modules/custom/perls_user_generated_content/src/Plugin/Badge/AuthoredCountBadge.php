<?php

namespace Drupal\perls_user_generated_content\Plugin\Badge;

use Drupal\achievements\Entity\AchievementEntity;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\badges\BadgePluginBase;
use Drupal\badges\Service\BadgeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Badge for authoring a certain amount of content.
 *
 * @Badge(
 *   id = "authored_count_badge_plugin",
 *   label = @Translation("Authored Count"),
 *   description = @Translation("Authored Count - Badge gets unlocked when a user authors a certain amount and type of content."),
 * )
 */
class AuthoredCountBadge extends BadgePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The badge Service.
   *
   * @var \Drupal\badges\Service\BadgeService
   */
  protected $badgeService;

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
      $container->get('entity_type.manager')
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
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->badgeService = $badge_service;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(AchievementEntity $achievement = NULL) {
    $count = 1;
    $selected_bundles = [];
    $selected_states = [];
    if ($achievement !== NULL &&  $achievement->getThirdPartySetting('badges', $this->getPluginId())) {
      $count = $this->getStoredValue($achievement, 'required_count');
      $selected_bundles = $this->getStoredValue($achievement, 'selected_bundles');
      $selected_states = $this->getStoredValue($achievement, 'selected_states');
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

    $return_array['selected_states'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select moderation states that should be counted.'),
      '#options' => $this->getAllowedModerationStates(),
      '#default_value' => $selected_states,
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
    // Award badge if a user has authored enough content.
    $badges = $this->badgeService->getBadgesByType($this->getPluginId());

    $unlocked_badges = $this->badgeService->getUnlockedBadges($user);

    // For each badge load the associated term with children.
    foreach ($badges as $badge_id => $badge) {
      // We want to get a count on the number of
      // If already unlocked continue.
      if (isset($unlocked_badges[$badge_id])) {
        continue;
      }
      // If an entity is passed with the update check to see
      // if this badge is affected by that entity. If no entity is passed
      // we will rely on the database.
      $count_current_entity = 0;
      if (isset($updates['entity'])) {
        if (
          !in_array($updates['entity']->bundle(), $this->getSelectedBundles($badge))
          || !in_array($updates['entity']->moderation_state->value, $this->getSelectedStates($badge))
          ) {
          continue;
        }
        // If we get to here the provided entity should be counted.
        // The entity query below won't pick up changes to this entity until
        // after this request completes so we add one to the count.
        $count_current_entity = 1;
      }
      // If required count is not set just skip badge.
      $required_count = $this->getStoredValue($badge, 'required_count');
      if (!$required_count || $required_count === 0) {
        continue;
      }
      // Cannot get moderation states from single entity query
      // so need to do search in two steps.
      $ids = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery()
        ->condition('uid', $user->id())
        ->condition('type', $this->getSelectedBundles($badge), 'IN')
        ->execute();
      if (empty($ids)) {
        continue;
      }
      // We have a list of authored content now check status.
      $items = $this->entityTypeManager
        ->getStorage('content_moderation_state')
        ->getQuery()
        ->condition('uid', $user->id())
        ->condition('moderation_state', $this->getSelectedStates($badge), 'IN')
        ->condition('content_entity_type_id', 'node')
        ->condition('content_entity_id', $ids, 'IN')
        ->execute();
      // If all entities are flagged award badge.
      if (count($items) + $count_current_entity >= $required_count) {
        $this->badgeService->awardBadge($user, $badge_id);
      }

    }
  }

  /**
   * Return the entity bundles this badge can be awared for.
   */
  protected function getAllowedEntityBundles() {
    return node_type_get_names();
  }

  /**
   * Return the entity bundles this badge can be awared for.
   */
  protected function getAllowedModerationStates() {
    return [
      'draft' => $this->t('Draft'),
      'review' => $this->t('Review'),
      'published' => $this->t('Published'),
      'archived' => $this->t('Archived'),
    ];
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
   * Get an entity query friendly version of the selected moderation states.
   */
  protected function getSelectedStates(AchievementEntity $achievement) {
    $selected_states = $this->getStoredValue($achievement, 'selected_states');
    $bundles = [];
    if (!$selected_states) {
      return $bundles;
    }
    foreach ($selected_states as $id => $key) {
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
