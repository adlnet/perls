<?php

namespace Drupal\badges;

use Drupal\achievements\Entity\AchievementEntity;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an base class for Badge plugins.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_badge_info_alter(). The definition includes the
 * following keys:
 * - id: The unique, system-wide identifier of the recommendation class.
 * - label: The human-readable name of the recommendation class, translated.
 * - description: A human-readable description for the recommendation class,
 *   translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @Badge(
 *   id = "xapi_badge_plugin",
 *   label = @Translation("XAPI Badge Plugin"),
 *   description = @Translation("Badge that gets unlocked by xapi events.")
 * )
 * @endcode
 *
 * @see \Drupal\badges\Annotation\Badge
 * @see \Drupal\badges\BadgePluginManager
 * @see \Drupal\badges\BadgePluginInterface
 * @see plugin_api
 */
abstract class BadgePluginBase extends PluginBase implements BadgePluginInterface, ContainerFactoryPluginInterface {

  /**
   * The logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('Badge Plugin')
    );
  }

  /**
   * Constructor for Badge Plugin Base.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerInterface $logger_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(AchievementEntity $achievement = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function updateConfigWithBadgeSettings(FormStateInterface $form_state, AchievementEntity $achievement) {}

  /**
   * {@inheritdoc}
   */
  public function removeBadgeSettingsFromConfig(AchievementEntity $achievement) {
    $achievement->unsetThirdPartySetting('badges', $this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  public function getBadgeTypeLabel() {
    return $this->getPluginDefinition()['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function updateUserProgress(AccountInterface $user, array $updates = NULL) {
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getPluginDefinition()['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->getPluginDefinition()['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage(AchievementEntity $achievement) {
    // Default storage location is the achievement Id.
    return $achievement->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultImage($type) {
    return NULL;
  }

}
