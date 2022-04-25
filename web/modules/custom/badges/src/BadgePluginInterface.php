<?php

namespace Drupal\badges;

use Drupal\achievements\Entity\AchievementEntity;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an interface for Badge plugins.
 *
 * Consists of general plugin methods and methods specific to
 * badge operation.
 *
 * @see \Drupal\badges\Annotation\Badge
 * @see \Drupal\badges\BadgePluginManager
 * @see \Drupal\badges\BadgePluginBase
 * @see plugin_api
 */
interface BadgePluginInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Update settings page configuration form for this plugin.
   *
   * This functions allows this plugin to add details to the badge
   * settings configuration form.
   */
  public function buildConfigurationForm(AchievementEntity $achievement = NULL);

  /**
   * Get an array of data to save to configuration from this plugin.
   *
   * This functions allows this plugin choose what data it needs to
   * save from the configuration entity.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param \Drupal\achievement\Entity\AchievementEntity $achievement
   *   The achievement entity.
   */
  public function updateConfigWithBadgeSettings(FormStateInterface $form_state, AchievementEntity $achievement);

  /**
   * Remove any settings you added to config here.
   */
  public function removeBadgeSettingsFromConfig(AchievementEntity $achievement);

  /**
   * Get a human readable label for this badge type.
   */
  public function getBadgeTypeLabel();

  /**
   * Update Users badge progression.
   *
   * This method is called to update a given users progress towards all badges
   * of this type.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to be updated.
   * @param array $updates
   *   The array containing the information needed by plugin. This is different
   *   for every plugin type.
   */
  public function updateUserProgress(AccountInterface $user, array $updates = NULL);

  /**
   * Get the human readable name of the plugin.
   */
  public function label();

  /**
   * Get the human reable description of the plugin.
   */
  public function getDescription();

  /**
   * Get the storage location for saved data for this badge.
   */
  public function getStorage(AchievementEntity $achievement);

  /**
   * Allow plugins to override default images.
   */
  public function getDefaultImage($type);

}
