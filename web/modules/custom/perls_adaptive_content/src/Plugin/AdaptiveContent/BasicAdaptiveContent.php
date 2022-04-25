<?php

namespace Drupal\perls_adaptive_content\Plugin\AdaptiveContent;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\perls_adaptive_content\AdaptiveContentPluginBase;

/**
 * Basic Adaptive Content Plugin.
 *
 * @AdaptiveContent(
 *   id = "basic_adaptive_content_plugin",
 *   label = @Translation("Skip content related to correctly answered questions"),
 *   description = @Translation("This plugin marks all content in a course complete based on answers to any questions associated with those objects."),
 * )
 */
class BasicAdaptiveContent extends AdaptiveContentPluginBase {

  /**
   * {@inheritdoc}
   */
  public function processTestAttempt(Node $test, ParagraphInterface $test_attempt, AccountInterface $user) {
    $learning_objects_in_course = $this->contentObjectsInCourse($test);
    $test_answers = $test_attempt->field_attempted_answers->referencedEntities();
    $test_outcome = [];
    foreach ($test_answers as $answer) {
      $is_correct = $answer->field_answer_correct->value;
      $quiz_card = $answer->field_quiz_card->referencedEntities();
      // No quiz card set we can't do anything so skip.
      if (empty($quiz_card)) {
        continue;
      }
      $quiz_card = reset($quiz_card);
      if (empty($quiz_card->field_parent_content)) {
        continue;
      }
      $related_content = $quiz_card->field_parent_content->referencedEntities();
      foreach ($related_content as $content) {
        // Only interested in learning objects in this course.
        if (!in_array($content->id(), $learning_objects_in_course)) {
          continue;
        }
        // Store the result in an array.
        if (isset($test_outcome[$content->id()])) {
          // Already have a record so just amend it.
          $test_outcome[$content->id()]['success'] = ($is_correct) ? $test_outcome[$content->id()]['success'] : FALSE;
        }
        else {
          // Create a record for this content.
          $test_outcome[$content->id()] = [
            'success' => $is_correct ? TRUE : FALSE,
            'content' => $content,
          ];
        }
      }
    }
    // Flag the passing items as complete.
    $complete_flag = $this->flagService->getFlagById('completed');
    foreach ($test_outcome as $id => $data) {
      $complete_flagging = $this->flagService->getFlagging($complete_flag, $data['content'], $user);
      if ($complete_flagging) {
        if ($complete_flagging->hasField('field_completion_source')) {
          $complete_flagging->field_completion_source = ($data['success']) ? 'adaptive' : 'needs_review';
          $complete_flagging->save();
        }
      }
      else {
        // No flagging so just mark complete if successful.
        if ($data['success']) {
          $complete_flagging = $this->flagService->flag($complete_flag, $data['content'], $user);
          if ($complete_flagging->hasField('field_completion_source')) {
            $complete_flagging->field_completion_source = 'adaptive';
            $complete_flagging->save();
          }
        }
      }

    }
  }

}
