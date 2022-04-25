<?php

namespace Drupal\perls_adaptive_content\Plugin\AdaptiveContent;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\perls_adaptive_content\AdaptiveContentPluginBase;
use Drupal\perls_adaptive_content\AdaptiveContentPluginInterface;

/**
 * Difficulty Adaptive Content Plugin.
 *
 * @AdaptiveContent(
 *   id = "difficulty_adaptive_content_plugin",
 *   label = @Translation("Enable Adaptive Content by Difficulty"),
 *   description = @Translation("This plugin marks all content in a course complete based on difficulty of the learning object."),
 *   deriver = "Drupal\perls_adaptive_content\Plugin\AdaptiveContent\Derivative\DifficultyAdaptiveContentDeriver"
 * )
 */
class DifficultyAdaptiveContent extends AdaptiveContentPluginBase implements ContainerFactoryPluginInterface, AdaptiveContentPluginInterface {
  /**
   * The default pass mark.
   */
  const DEFAULT_PASS_MARK = 0.99;

  /**
   * {@inheritdoc}
   */
  public function processTestAttempt(Node $test, ParagraphInterface $test_attempt, AccountInterface $user) {
    $learning_objects_in_course = $this->loadLearningObjectsInCourse($test);
    $pass_mark = self::DEFAULT_PASS_MARK;
    if ($test->hasField('field_pass_mark')) {
      $pass_mark = $test->field_pass_mark->value / 100;
    }
    $test_result = 0;
    if ($test_attempt->hasField('field_test_result')) {
      $test_result = $test_attempt->field_test_result->value;
    }
    $test_complete = FALSE;
    if ($test_attempt->hasField('field_test_complete')) {
      $test_complete = $test_attempt->field_test_complete->value;
    }
    // Need to figure out what difficulties are being skipped.
    $plugin_difficulty = $this->getPluginDifficulty();
    $difficulty_levels = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => 'difficulty']);
    $levels_to_skip = [];
    foreach ($difficulty_levels as $difficulty) {
      if ($difficulty->getWeight() <= $plugin_difficulty->getWeight()) {
        $levels_to_skip[] = $difficulty->id();
      }
    }
    // Flag the passing items as complete.
    $complete_flag = $this->flagService->getFlagById('completed');
    foreach ($learning_objects_in_course as $id => $lo) {
      // Make sure it has difficulty field.
      if (!$lo->hasField('field_difficulty')) {
        continue;
      }
      $difficulty = $lo->field_difficulty->referencedEntities();
      if (empty($difficulty)) {
        continue;
      }
      $difficulty = reset($difficulty);

      if (!in_array($difficulty->id(), $levels_to_skip)) {
        continue;
      }
      $complete_flagging = $this->flagService->getFlagging($complete_flag, $lo, $user);
      if ($complete_flagging) {
        if ($complete_flagging->hasField('field_completion_source')) {
          $complete_flagging->field_completion_source = ($test_result >= $pass_mark) ? 'adaptive' : 'needs_review';
          $complete_flagging->save();
        }
      }
      else {
        // No flagging so just mark complete if successful.
        if ($test_result >= $pass_mark) {
          $complete_flagging = $this->flagService->flag($complete_flag, $lo, $user);
          if ($complete_flagging->hasField('field_completion_source')) {
            $complete_flagging->field_completion_source = 'adaptive';
            $complete_flagging->save();
          }
        }
      }

    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedback(EntityInterface $test, $result, $correctly_answered, $question_count) {
    $difficulty = $this->getPluginDifficulty();

    $pass_mark = self::DEFAULT_PASS_MARK;
    if ($test->hasField('field_pass_mark')) {
      $pass_mark = $test->field_pass_mark->value / 100;
    }
    if ($result >= $pass_mark) {
      return $this->t('<p>You answered @correct out of @count correctly. </p><p>Based on your performance we have marked all @term content complete.</p>',
        [
          '@correct' => $correctly_answered,
          '@count' => $question_count,
          '@term' => $difficulty->label(),
        ]
      );
    }
    else {
      return $this->t(
        '<h2>@result %</h2><div>You answered <span class="correct">@correct</span> out of <span class="total">@total</span> correct.</div>',
        [
          '@result' => intval($result * 100),
          '@correct' => $correctly_answered,
          '@total' => $question_count,
        ]
      );
    }
  }

  /**
   * Get the difficulty term associated with this plugin.
   */
  protected function getPluginDifficulty() {
    $selected_difficulty = $this->getPluginDefinition()['extra_data']['term_id'];
    $difficulty = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(
      [
        'vid' => 'difficulty',
        'tid' => $selected_difficulty,
      ]
    );
    return reset($difficulty);
  }

}
