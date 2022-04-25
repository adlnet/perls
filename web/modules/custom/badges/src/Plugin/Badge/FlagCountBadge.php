<?php

namespace Drupal\badges\Plugin\Badge;

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
 * Badge for achieving a certain count of flags.
 *
 * @Badge(
 *   id = "flag_count_badge_plugin",
 *   label = @Translation("Completion Count"),
 *   description = @Translation("Completion Count - Badge that gets unlocked by flagging content."),
 *   deriver = "Drupal\badges\Plugin\Badge\Derivative\FlagCountBadgeDeriver"
 * )
 */
class FlagCountBadge extends BadgePluginBase implements ContainerFactoryPluginInterface {

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
   * Constructor for Assign Badge Action.
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

    $flag = $this->getFlag();

    // For each badge load the associated term with children.
    foreach ($badges as $badge_id => $badge) {
      // We want to get a count on the number of
      // If already unlocked continue.
      if (isset($unlocked_badges[$badge_id])) {
        continue;
      }
      // If an entity is passed with the update check to see
      // if this badge is affected by that entity.
      if (
        isset($updates['entity']) &&
        !in_array($updates['entity']->bundle(), $this->getSelectedBundles($badge))
      ) {
        continue;
      }
      // If required count is not set just skip badge.
      $required_count = $this->getStoredValue($badge, 'required_count');
      if (!$required_count || $required_count === 0) {
        continue;
      }
      $flagged_entities = $this->entityTypeManager->getStorage('flagging')->loadByProperties(
        [
          'flag_id' => $flag->id(),
          'uid' => $user->id(),
        ]
      );
      if (!$flagged_entities || empty($flagged_entities)) {
        continue;
      }

      $count = 0;
      foreach ($flagged_entities as $entity) {
        if (in_array($entity->getFlaggable()->bundle(), $this->getSelectedBundles($badge))) {
          $count++;
        }
      }
      // If all entities are flagged award badge.
      if ($count >= $required_count) {
        $this->badgeService->awardBadge($user, $badge_id);
      }

    }
  }

  /**
   * Get the flag object this badge is used with.
   */
  protected function getFlag() {
    $flag_id = $this->getPluginDefinition()['extra_data']['flag'];
    return $this->flagService->getFlagById($flag_id);
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
