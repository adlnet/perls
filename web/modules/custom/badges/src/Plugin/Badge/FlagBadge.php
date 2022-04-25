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
 * Badge for flagged content.
 *
 * @Badge(
 *   id = "flag_badge_plugin",
 *   label = @Translation("Completion"),
 *   description = @Translation("Completed - Badge that gets unlocked by flagging content."),
 *   deriver = "Drupal\badges\Plugin\Badge\Derivative\FlagBadgeDeriver"
 * )
 */
class FlagBadge extends BadgePluginBase implements ContainerFactoryPluginInterface {

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
    $entities = [];
    if ($achievement !== NULL &&  $achievement->getThirdPartySetting('badges', $this->getPluginId())) {
      $entities = $this->getEntities($achievement);
    }
    return [
      '#type' => 'entity_autocomplete',
      '#target_type' => $this->getEntityType(),
      '#tags' => TRUE,
      '#validate_reference' => TRUE,
      '#maxlength' => 5000,
      '#selection_settings' => [
        'target_bundles' => $this->getEntityBundles(),
      ],
      '#title' => $this->t('Content to complete'),
      '#description' => $this->t('This badge is awarded when a user has completed all of the listed content.'),
      '#default_value' => $entities,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function updateConfigWithBadgeSettings(FormStateInterface $form_state, AchievementEntity $achievement) {
    $data = [
      'entities' => $form_state->getValue($this->getPluginId()),
    ];
    $achievement->setThirdPartySetting('badges', $this->getPluginId(), $data);
  }

  /**
   * {@inheritdoc}
   */
  public function updateUserProgress(AccountInterface $user, array $updates = NULL) {
    // Award badge if a user has completed all the content linked to this badge.
    $badges = $this->badgeService->getBadgesByType($this->getPluginId());

    $unlocked_badges = $this->badgeService->getUnlockedBadges($user);

    $flag = $this->getFlag();

    // For each badge load the associated term with children.
    foreach ($badges as $badge_id => $badge) {
      // If already unlocked continue.
      if (isset($unlocked_badges[$badge_id])) {
        continue;
      }
      // Get associated term label.
      $entities = $this->getEntities($badge);
      if (!$entities || empty($entities)) {
        continue;
      }
      $award = TRUE;
      foreach ($entities as $entity) {
        if (!$this->flagService->getFlagging($flag, $entity, $user)) {
          // Required entity not flagged.
          $award = FALSE;
          break;
        }
      }
      // If all entities are flagged award badge.
      if ($award) {
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
  protected function getEntityBundles() {
    $flag = $this->getFlag();
    $bundles = $flag->getBundles();
    $return_array = [];
    foreach ($bundles as $bundle) {
      $return_array[$bundle] = $bundle;
    }
    return $return_array;
  }

  /**
   * Get a list of entities that need to be flagged for badge to be awarded.
   */
  protected function getEntities(AchievementEntity $achievement) {
    $previous_value = $achievement->getThirdPartySetting('badges', $this->getPluginId())['entities'];
    $entity_ids = array_map(function (array $item) {
        return $item['target_id'];
    },
    $previous_value);
    return $this->entityTypeManager->getStorage($this->getEntityType())->loadMultiple($entity_ids);
  }

}
