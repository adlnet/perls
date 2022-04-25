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
 * Random Recommendation engine plugin.
 *
 * @Badge(
 *   id = "touchpoint_badge_plugin",
 *   label = @Translation("Touchpoint Completion"),
 *   description = @Translation("TouchPoint Completion - Badge that gets unlocked by completing a touchpoint.")
 * )
 */
class TouchPointBadge extends BadgePluginBase implements ContainerFactoryPluginInterface {

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
    $previous_value = '';
    if ($achievement !== NULL &&  $achievement->getThirdPartySetting('badges', $this->getPluginId())) {
      $previous_value = $achievement->getThirdPartySetting('badges', $this->getPluginId())['term'];
    }
    return [
      '#type' => 'textfield',
      '#title' => $this->t('TouchPoint Term name'),
      '#description' => $this->t('This badge is awarded when a user has completed all content inside this term.'),
      '#default_value' => $previous_value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function updateConfigWithBadgeSettings(FormStateInterface $form_state, AchievementEntity $achievement) {
    $data = [
      'term' => $form_state->getValue($this->getPluginId()),
    ];
    $achievement->setThirdPartySetting('badges', $this->getPluginId(), $data);
  }

  /**
   * {@inheritdoc}
   */
  public function updateUserProgress(AccountInterface $user, array $updates = NULL) {
    // Award a badge if a user has completed all content inside of touchpoint.
    // Get a list of all badges of this type.
    $badges = $this->badgeService->getBadgesByType($this->getPluginId());

    $unlocked_badges = $this->badgeService->getUnlockedBadges($user);

    $complete_flag = $this->flagService->getFlagById('elearning_content_completion');

    // For each badge load the associated term with children.
    foreach ($badges as $badge_id => $badge) {
      // If already unlocked continue.
      if (isset($unlocked_badges[$badge_id])) {
        continue;
      }
      // Get associated term label.
      $term_name = $badge->getThirdPartySetting('badges', 'touchpoint_badge_plugin')['term'];
      if (!$term_name || $term_name == '') {
        continue;
      }
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(
        [
          'vid' => 'activity_group',
          'name' => $term_name,
          'status' => '1',
        ]
      );
      $term = reset($term);
      if (!$term) {
        continue;
      }
      $children = $this->entityTypeManager->getStorage('node')->loadByProperties(
        [
          'field_activity_groups' => $term->id(),
          'type' => [
            'activity',
            'link',
          ],
          'status' => 1,
        ]
      );
      $is_complete = TRUE;
      foreach ($children as $node) {
        if (!$this->flagService->getFlagging($complete_flag, $node, $user)) {
          // Child node not complete so touchpoint is not complete.
          $is_complete = FALSE;
        }
      }
      // If all children are complete award badge.
      if ($is_complete) {
        $this->badgeService->awardBadge($user, $badge_id);
      }

    }
  }

}
