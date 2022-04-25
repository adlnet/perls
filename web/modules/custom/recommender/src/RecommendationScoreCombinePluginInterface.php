<?php

namespace Drupal\recommender;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\recommender\Entity\RecommendationCandidate;

;

/**
 * Defines an interface for Recommendation Engine plugins.
 *
 * Consists of general plugin methods and methods specific to
 * recommendation engine operation.
 *
 * @see \Drupal\recommender\Annotation\RecommendationScoreCombinePlugin
 * @see \Drupal\recommender\RecommendationScoreCombinePluginManager
 * @see \Drupal\recommender\RecommendationScoreCombinePluginBase
 * @see plugin_api
 */
interface RecommendationScoreCombinePluginInterface extends PluginInspectionInterface, DerivativeInspectionInterface, PluginFormInterface {

  /**
   * Return the translated name of this plugin.
   */
  public function label();

  /**
   * Return the description of this plugin.
   */
  public function getDescription();

  /**
   * Recommendation Candidate get score.
   *
   * Given a recommendation candidate, combine/collate scores
   * into a single score.
   */
  public function getScore(RecommendationCandidate $candidate);

  /**
   * Given a recommendation candidate get the recommendation reason.
   */
  public function getReason(RecommendationCandidate $candidate, $langcode = NULL);

}
