<?php

namespace Drupal\recommender;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an interface for Recommendation Engine plugins.
 *
 * Consists of general plugin methods and methods specific to
 * recommendation engine operation.
 *
 * @see \Drupal\recommender\Annotation\RecommendationEngine
 * @see \Drupal\recommender\RecommendationEnginePluginManager
 * @see \Drupal\recommender\RecommendationEnginePluginBase
 * @see plugin_api
 */
interface RecommendationEnginePluginInterface extends PluginInspectionInterface, DerivativeInspectionInterface, PluginFormInterface {

  /**
   * Recommendation Stage: generate_candidates.
   *
   * In this stage, plugins nominate nodes for recommendation.
   */
  const STAGE_GENERATE_CANDIDATES = 'generate_candidates';

  /**
   * Recommendation Stage: alter_candidates.
   *
   * In this stage, plugins can remove any candidates
   * nominated by other plugins.
   */
  const STAGE_ALTER_CANDIDATES = 'alter_candidates';

  /**
   * Recommendation Stage: score_candidates.
   *
   * In this stage, plugins score the remaining candidates.
   * This gives the basic recommendation score for each node.
   */
  const STAGE_SCORE_CANDIDATES = 'score_candidates';

  /**
   * Recommendation Stage: rerank_candidate.
   *
   * In this stage, scores can be tweaked after the final score
   * has been calculated.
   */
  const STAGE_RERANK_CANDIDATES = 'rerank_candidates';

  /**
   * Recommendation Stage: alter_recommendation_on_load.
   */
  const ALTER_RECOMMENDATION_ON_LOAD = 'alter_recommendation_on_load';

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
   * Tell recommendation engine to queue this user for recommendations.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user that needs recommendations.
   */
  public function queueUserForRecommendations(AccountInterface $user);

  /**
   * Get recommendations for this user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user that needs recommendations.
   * @param int $count
   *   The number of recommendations to return.
   * @param bool $now
   *   Process this request immediately.
   *
   * @return array
   *   An array of recommendations.
   */
  public function getUserRecommendations(AccountInterface $user, $count = 5, $now = FALSE);

  /**
   * Check to see if user recommendations are ready.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user that needs recommendations.
   *
   * @return bool
   *   TRUE if recommendations are ready, FALSE otherwise.
   */
  public function userRecommendationsReady(AccountInterface $user);

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

  /**
   * Return the translated name of this plugin.
   */
  public function label();

  /**
   * Return the description of this plugin.
   */
  public function getDescription();

  /**
   * Does this plug in support a certain recommendation stage.
   */
  public function supportsStage($stage_id);

  /**
   * Get the weight of this plugin for a particular stage.
   */
  public function getWeight($stage_id = NULL);

  /**
   * Set the weight of this plugin for a particular stage.
   */
  public function setWeight($stage_id, $weight);

  /**
   * Generate recommendation candidates for this user.
   */
  public function generateCandidates(AccountInterface $user);

  /**
   * Score recommendation candidates based on your plugin criteria.
   *
   * Scores should be between 0 and 1 but this is not enforced.
   */
  public function scoreCandidates(array $candidates, AccountInterface $user);

  /**
   * Alter recommendation candidates.
   *
   * Add or remove recommendation candidates for this user
   * after generation phase.
   */
  public function alterCandidates(array $candidates, AccountInterface $user);

  /**
   * Rerank the recommednation candidates.
   *
   * Change scores of candidates, add/remove candidates after final score has
   * been calculated.
   */
  public function rerankCandidates(array $candidates, AccountInterface $user);

}
