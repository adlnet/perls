<?php

namespace Drupal\perls_adaptive_content;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Class AdaptiveContentService.
 */
interface AdaptiveContentServiceInterface {

  /**
   * Process test for adaptive content.
   */
  public function processTest(NodeInterface $node, AccountInterface $user);

  /**
   * Get an array of all available adaptive content plugins.
   *
   * @return array
   *   An array of adaptive learning plugins.
   */
  public function getAdaptiveContentPlugins();

  /**
   * Get a particular adaptive learning plugin.
   *
   * @return Drupal\perls_adaptive_content\AdaptiveContentPluginInterface
   *   An instance of plugin with given id or Null.
   */
  public function getAdaptiveContentPlugin(string $id = NULL);

  /**
   * Get feedback for given tests and scores from plugins.
   */
  public function getTestFeedback(EntityInterface $test, $result, $correctly_answered, $question_count);

  /**
   * Check to see if a test is adaptive.
   */
  public function isTestAdaptive(EntityInterface $test);

}
