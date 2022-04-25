<?php

namespace Drupal\perls_adaptive_content;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\ParagraphInterface;

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
interface AdaptiveContentPluginInterface {

  /**
   * Return the translated name of this plugin.
   */
  public function label();

  /**
   * Return the description of this plugin.
   */
  public function getDescription();

  /**
   * Process a test attempt to mark content as complete.
   */
  public function processTestAttempt(Node $test, ParagraphInterface $test_attempt, AccountInterface $user);

  /**
   * Get Feedback for tests using this adaptive learning system.
   */
  public function getFeedback(EntityInterface $test, $result, $correctly_answered, $question_count);

}
