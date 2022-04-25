<?php

namespace Drupal\prompts\Plugin\Prompt;

use Drupal\node\Entity\Node;
use Drupal\prompts\Prompt\PromptPluginBase;
use Drupal\user\UserInterface;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Plugin to ask feedback from user about course complexity.
 *
 * @Prompt(
 *   id = "course_feedback_prompt",
 *   label = @Translation("Course feedback"),
 *   description = @Translation("Ask user about the latest completed course."),
 *   webform = "completed_course_feedback",
 *   limit = "24",
 *   questionField = "how_difficult"
 * )
 */
class CourseFeedback extends PromptPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getUserQuestions(UserInterface $user) {
    if ($this->isTimeToAsk($user)) {
      $generated_question = $this->getPreGeneratedQuestions($user, '1');
      if (!empty($generated_question)) {
        return $generated_question;
      }
      $courses = $this->getCompletedCourses($user);
      if ($courses) {
        foreach ($courses as $course_id) {
          $course = Node::load($course_id);
          $questions = [];
          if ($question = $this->generateNewQuestion($course, $user->id())) {
            $questions[] = $question;
          }
          return $questions;

        }
      }
    }

    if ($this->debug) {
      // @todo Add test quiz and course then it can return a generated question.
    }
    return NULL;
  }

  /**
   * Gives back the recent completed course in the last 24 hours.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user who completed the course.
   */
  public function getCompletedCourses(UserInterface $user) {
    $courses = &drupal_static(__FUNCTION__);
    if (isset($courses)) {
      return $courses;
    }

    $start_date = strtotime(sprintf('-%d hours', $this->timePeriod));
    $end_date = time();
    $query = $this->database->select('flagging', 'f')
      ->condition('flag_id', 'completed', '=')
      ->condition('uid', $user->id(), '=')
      ->condition('created', [$start_date, $end_date], 'BETWEEN')
      ->condition('entity_type', 'node', '=');
    $query->join('node', 'n', 'f.entity_id = n.nid');
    $query->fields('n', ['nid']);
    $query->condition('n.type', 'course', '=');
    $query->orderBy('f.id', 'DESC');
    $courses = $query->execute()->fetchAssoc();

    return $courses;
  }

  /**
   * {@inheritdoc}
   */
  public function debugInstall() {
    // @todo Implement debugInstall() method.
  }

  /**
   * {@inheritdoc}
   */
  public function debugUninstall() {
    // @todo Implement debugUninstall() method.
  }

  /**
   * {@inheritdoc}
   */
  public function debugClearData(UserInterface $user) {
    // @todo Implement debugClearData() method.
  }

  /**
   * {@inheritdoc}
   */
  public function actOnSubmission(WebformSubmission $submission) {
    // @todo Implement actOnSubmission() method.
  }

}
