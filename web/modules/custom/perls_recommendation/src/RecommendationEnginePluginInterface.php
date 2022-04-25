<?php

namespace Drupal\perls_recommendation;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;

/**
 * Defines an interface for Recommendation Engine plugins.
 *
 * Consists of general plugin methods and methods specific to
 * recommendation engine operation.
 *
 * @see \Drupal\perls_recommendation\Annotation\RecommendationEngine
 * @see \Drupal\perls_recommendation\RecommendationEnginePluginManager
 * @see \Drupal\perls_recommendation\RecommendationEnginePluginBase
 * @see plugin_api
 */
interface RecommendationEnginePluginInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Get the status of the connected server.
   *
   * @return array
   *   Returns an array in the following format.
   *
   * @code
   *     ['status' => TRUE|FALSE,
   *      'description' => "details to pass on to status report"
   *     ]
   * @endcode
   */
  public function getStatus();

  /**
   * Update settings page configuration form for this plugin.
   *
   * This functions allows this plugin to add details to the recommendation
   * engine settings configuration form.
   *
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param \Drupal\Core\Config\Config $config
   *   The modules configuration object which can be used to store values.
   *
   * @return array
   *   Returns the altered form array.
   */
  public function buildConfigurationForm(array &$form, FormStateInterface $form_state, Config $config);

  /**
   * Validate settings page configuration form for this plugin.
   *
   * This functions allows this plugin to add validation to submitted data
   * on the recommendation engine settings configuration form.
   *
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state);

  /**
   * Submit settings page configuration form for this plugin.
   *
   * This functions allows this plugin to handle data submitted
   * via the recommendation engine settings configuration form.
   *
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param \Drupal\Core\Config\Config $config
   *   The modules configuration object which can be used to store values.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state, Config $config);

  /**
   * Tell recommendation engine to queue this user for recommendations.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user that needs recommendations.
   */
  public function queueUserForRecommendations(UserInterface $user);

  /**
   * Get recommendations for this user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user that needs recommendations.
   * @param int $count
   *   The number of recommendations to return.
   * @param bool $now
   *   Process this request immediately.
   *
   * @return array
   *   An array of recommendations.
   */
  public function getUserRecommendations(UserInterface $user, $count = 5, $now = FALSE);

  /**
   * Check to see if user recommendations are ready.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user that needs recommendations.
   *
   * @return bool
   *   TRUE if recommendations are ready, FALSE otherwise.
   */
  public function userRecommendationsReady(UserInterface $user);

  /**
   * Update the recommendation engine with changes to an entity.
   *
   * @param Drupal\Core\Entity\EntityInterface $entity
   *   The entity that was updated.
   * @param bool $use_queue
   *   True if you want to queue items until next cron run, False otherwise.
   * @param bool $delete_entity
   *   True if the update is a delete operation, FALSE for all other operations.
   */
  public function updateEntity(EntityInterface $entity, $use_queue = FALSE, $delete_entity = FALSE);

  /**
   * Check if recommendation needs updates about this entity type.
   *
   * @param Drupal\Core\Entity\EntityInterface $entity
   *   The entity that was updated.
   *
   * @return bool
   *   TRUE if plugin needs entity updates form this entity. FALSE otherwise.
   */
  public function requiresUpdateFromEntity(EntityInterface $entity);

  /**
   * Reset the recommendation graph to initial state.
   *
   * This function deletes all node and edge data from the current
   * recommendation engine graph. It is a descructive process that cannot
   * be undone. It might be necessary to use this function if the remote
   * recommendation engine goes out of sync with local environment.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function resetGraph();

  /**
   * Sync the recommendation graph with local storage.
   *
   * This method attempts to write any updates from local cms
   * out to the recommendation engine.
   *
   * @param int $batch_size
   *   The max number of items to sync before returning to caller.
   *
   * @return bool
   *   TRUE is successful, otherwise false.
   */
  public function syncGraph($batch_size = 100);

}
