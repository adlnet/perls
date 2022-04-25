<?php

namespace Drupal\recommender;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Class Recommendation Service Interface.
 */
interface RecommendationServiceInterface {

  /**
   * Process Recommendation Queue.
   *
   * The process recommendation queue method is used by cron
   * to request that users who have been queue for recommendations
   * get processed.
   */
  public function processRecommendationQueue($limit = -1);

  /**
   * Clean up Recommendation History.
   *
   * This process attempts to delete old recommendation history.
   */
  public function cleanUpRecommendationHistory($limit = -1);

  /**
   * Mark users recommendations stale.
   *
   * If generate recommendations on login is enabled, users
   * recommendations can be marked as stale after a number of
   * weeks. This will cause the system to regenerate recommendations
   * for that user on next login.
   */
  public function markRecommendationStatusAsStale();

  /**
   * Immediately queue and build all user recommendations.
   */
  public function buildAllUserRecommendations($build_now = FALSE);

  /**
   * Build user recommendation for a single user.
   *
   * If settings have cron enabled the user will be queued
   * for recommendations with the supplied priority.
   *
   * @param Drupal\Core\Session\AccountInterface $user
   *   The user to build the recommendations for.
   * @param int $priority
   *   The priority this user should be given for new recommendations.
   * @param bool $now
   *   Ignore cron settings and build immediately.
   */
  public function buildUserRecommendations(AccountInterface $user, $priority = 0, $now = FALSE);

  /**
   * Check recommendation engine plugin health status.
   */
  public function checkStatus();

  /**
   * Reset Recommendation engine.
   *
   * @param Drupal\Core\Session\AccountInterface $user
   *   Optional, deletes status,candidates and scores for given user
   *   or all data if user is omitted.
   */
  public function resetUserRecommendations(AccountInterface $user = NULL);

  /**
   * Delete all user recommendations in the system.
   */
  public function deleteUserRecommendations();

  /**
   * Get an array of all available recommendation engine plugins.
   *
   * @param string $stage
   *   You can optionally supply a stage which will limit the
   *   array to plugins for that stage.
   * @param bool $return_only_active
   *   If true return only plugins that have been enabled else
   *   return all plugins for given stage. (default = false).
   *
   * @return array
   *   An array of recommendation plugins for the given stage
   *   or all if stage is null.
   */
  public function getRecommendationEnginePlugins($stage = NULL, $return_only_active = FALSE);

  /**
   * Get an array of all score combine plugins.
   *
   * @return array
   *   Returns an associative array with $plugin_id => $plugin for each
   *   available plugin.
   */
  public function getScoreCombinePlugins();

  /**
   * Get an array list of valid recommendation plugin stages.
   */
  public function getRecommendationStages();

  /**
   * Remove scores from associated plugin.
   */
  public function removePluginScores($plugin);

  /**
   * Get or create user recommendation status entity for a given user.
   *
   * @param Drupal\Core\Session\AccountInterface $user
   *   The user of interest.
   */
  public function getUserStatus(AccountInterface $user);

  /**
   * Get or Create a candidate entity for a user node pair.
   *
   * @param Drupal\Core\Session\AccountInterface $user
   *   The user of interest.
   * @param Drupal\node\NodeInterface $candidate
   *   The node to be used as candidate.
   */
  public function getCandidateEntity(AccountInterface $user, NodeInterface $candidate);

  /**
   * Check if recommendation service is using cron.
   */
  public function getUseCronRecommend();

  /**
   * Check if recommendations are build on user registeration.
   */
  public function shouldBuildOnRegistration();

  /**
   * Check if recommendations are build on user update.
   */
  public function shouldBuildOnUserUpdate();

  /**
   * Should build on user login.
   */
  public function shouldBuildOnUserLogin();

  /**
   * Should build with ajax.
   */
  public function shouldBuildWithAjax();

  /**
   * Recommendation ajax view name.
   */
  public function getRecommendationAjaxView();

  /**
   * Recommendation ajax view display id.
   */
  public function getRecommendationAjaxViewDisplayId();

  /**
   * Returns if a given user has recommendations.
   */
  public function hasRecommendations(UserInterface $user);

}
