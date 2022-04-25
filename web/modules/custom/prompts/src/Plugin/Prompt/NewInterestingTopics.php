<?php

namespace Drupal\prompts\Plugin\Prompt;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\prompts\Prompt\PromptPluginBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\UserInterface;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\user\Entity\User;

/**
 * Plugin to "prompts" new topics to user.
 *
 * @Prompt(
 *   id = "new_topics_prompt",
 *   label = @Translation("New interresting topics for users."),
 *   description = @Translation("This plugin will suggest new topics for users."),
 *   webform = "new_interesting_topic",
 *   limit = "24",
 *   questionField = "are_you_interested"
 * )
 */
class NewInterestingTopics extends PromptPluginBase {

  /**
   * Name of debug Term.
   *
   * @var string
   */
  protected $debugTermName = 'Test Interesting Topic & More';

  /**
   * Minimum number of LOs outside of users interest.
   *
   * @var int
   */
  protected $learningObjectCountMinimum = 3;

  /**
   * Debug Term Entity.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $debugTerm = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $database, $entity_type_manager);
    if ($this->debug) {
      $terms = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadByProperties([
          'name' => $this->debugTermName,
          'vid' => 'category',
        ]);
      $this->debugTerm = array_shift($terms);

      // Increase amount and frequency of prompts returned.
      // Set to one hour.
      $this->timePeriod = 1;
      // Only require one LO view outside of a learners interest.
      $this->learningObjectCountMinimum = 1;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUserQuestions(UserInterface $user) {
    $questions = [];
    if ($this->isTimeToAsk($user)) {
      $generate_questions = $this->getPreGeneratedQuestions($user);
      if (!empty($generate_questions)) {
        return $generate_questions;
      }
      elseif (!empty($this->getCompletedLearningObjectTopics($user, $this->learningObjectCountMinimum))) {
        $topics = $this->getNewInterestingTopics($user);
        foreach ($topics as $topic) {
          $term = Term::load($topic);
          if ($question = $this->generateNewQuestion($term, $user->id())) {
            $questions[] = $question;
          }
        }
        return $questions;
      }
      elseif ($questions = $this->getPreGeneratedQuestions($user, 'all')) {
        return $questions;
      }
    }

    if ($this->debug && $this->debugTerm) {
      $this->debugClearData($user);
      if ($question = $this->generateNewQuestion($this->debugTerm, $user->id())) {
        $questions[] = $question;
      }
      return $questions;
    }

    return FALSE;
  }

  /**
   * Gives back those topics, which would be interesting for a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   *
   * @return array
   *   A list of interesting topics ids.
   */
  protected function getNewInterestingTopics(UserInterface $user) {
    $completed_topics = $this->getCompletedLearningObjectTopics($user, $this->learningObjectCountMinimum);
    $topics_list = [];
    foreach ($completed_topics as $topic) {
      $topics_list[] = $topic->topic_id;
    }
    $asked_topics = $this->getAskedTopics($user);
    $interested_topics = $this->getUserInterestedTopics($user);
    // We subtract the asked and currently "have" topics from topics of
    // completed learning objects.
    return array_diff($topics_list, $asked_topics, $interested_topics);
  }

  /**
   * Count the completed learning objects in a time frame.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   * @param int $limit
   *   Number of completed learning objects under this limit there isn't enough
   *   completed content.
   *
   * @return mixed
   *   Number of completed learning objects.
   */
  protected function getCompletedLearningObjectTopics(UserInterface $user, $limit) {
    $new_completed_topics = &drupal_static(__FUNCTION__);
    if (isset($new_completed_topics)) {
      return $new_completed_topics;
    }
    // Set that it show completed flags for the last x hours.
    $user_current_topics = $this->getUserInterestedTopics($user);
    $start_date = strtotime(sprintf('-%d hours', $this->timePeriod));
    $end_date = time();
    // Select all completed flagging which belongs to this user and they were
    // created in the last x hours.
    $query = $this->database->select('flagging', 'f')
      ->condition('uid', $user->id(), '=')
      ->condition('entity_type', 'node', '=')
      ->condition('flag_id', 'completed', '=');
    // Filter only flagging which belongs to learning objects.
    $query->join('node', 'n', 'n.nid = f.entity_id');
    $query->condition('n.type', $this->getLearningObjects(), 'IN');
    $query->condition('created', [$start_date, $end_date], 'BETWEEN');
    // Filter out those topics which are part of user's "interested" topics.
    $query->leftJoin('node__field_topic', 'nft', 'n.nid = nft.entity_id');
    $query->addField('nft', 'field_topic_target_id', 'topic_id');
    if (!empty($user_current_topics)) {
      $query->condition('nft.field_topic_target_id', $user_current_topics, 'NOT IN');
    }
    $query->addExpression('count(nft.field_topic_target_id)', 'topics_count');
    $query->orderBy('nft.field_topic_target_id', 'DESC');
    $query->groupBy('nft.field_topic_target_id');
    $query->having('COUNT(uid) >= :matches', [':matches' => $limit]);
    $new_completed_topics = $query->execute()->fetchAll();
    return $new_completed_topics;
  }

  /**
   * Collects all asked topics.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   *
   * @return array
   *   List of topics ids.
   */
  protected function getAskedTopics(UserInterface $user) {
    $query = $this->database->select('webform_submission', 'ws')
      ->fields('ws', ['entity_id'])
      ->condition('webform_id', $this->getWebformId(), '=')
      ->condition('uid', $user->id(), '=')
      ->condition('entity_type', 'taxonomy_term', '=');
    return $query->distinct()->execute()->fetchAll(\PDO::FETCH_COLUMN);
  }

  /**
   * Gives back all interesting topics of a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   *
   * @return array
   *   List of topics ids.
   */
  protected function getUserInterestedTopics(UserInterface $user) {
    $topics = [];
    if ($user->hasField('field_interests')) {
      $entities = $user->get('field_interests')->referencedEntities();
      if ($entities) {
        foreach ($entities as $entity) {
          $topics[] = $entity->id();
        }
      }
    }
    return $topics;
  }

  /**
   * Sets new topics to user.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   * @param array $topics_list
   *   List of new topics.
   */
  public function updateUserTopics(UserInterface $user, array $topics_list) {
    if ($user->hasField('field_interests')) {
      foreach ($topics_list as $topic) {
        $user->get('field_interests')->appendItem($topic);
        $user->save();
      }
    }
  }

  /**
   * Gives back of machine name of learning objects.
   *
   * @return string[]
   *   A list of learning objects.
   */
  protected function getLearningObjects() {
    $field_config = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->load('node.field_topic');

    if ($field_config) {
      return array_values($field_config->getBundles());
    }
  }

  /**
   * Install "test_interesting_topic" category term.
   */
  public function debugInstall() {
    if ($this->debugTerm) {
      return;
    }
    Term::create([
      'name' => $this->debugTermName,
      'vid' => 'category',
    ])->save();
  }

  /**
   * Remove "test_interesting_topic" category term.
   *
   * Delete submission from this topic.
   */
  public function debugUninstall() {

    if (!$this->debugTerm) {
      return;
    }

    $query = $this->entityTypeManager->getStorage('webform_submission')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('webform_id', $this->getWebformId(), '=')
      ->condition('entity_type', 'taxonomy_term', '=')
      ->condition('entity_id', $this->debugTerm->id(), '=');

    $sids = $query->execute();

    $test_submissions = WebformSubmission::loadMultiple($sids);
    /** @var \Drupal\webform\Entity\WebformSubmission $submission */
    foreach ($test_submissions as $submission) {
      $user = User::load($submission->getOwnerId());
      $this->debugClearData($user);
    }
    $this->debugTerm->delete();
  }

  /**
   * Reset Debug data so it can be tested again.
   */
  public function debugClearData(UserInterface $user) {

    $query = $this->entityTypeManager->getStorage('webform_submission')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('webform_id', $this->getWebformId(), '=')
      ->condition('entity_type', 'taxonomy_term', '=')
      ->condition('entity_id', $this->debugTerm->id(), '=')
      ->condition('uid', $user->id(), '=');

    $sids = $query->execute();

    $test_submissions = WebformSubmission::loadMultiple($sids);
    /** @var \Drupal\webform\Entity\WebformSubmission $submission */
    foreach ($test_submissions as $submission) {
      $submission->delete();
    }
    if ($user->hasField('field_interests')) {
      $field_interests = $user->get('field_interests');
      $value = $field_interests->getValue();
      $deltas_to_be_removed = [];
      foreach ($value as $index => $target) {

        if ($this->debugTerm->id() === $target['target_id']) {
          $deltas_to_be_removed[] = $index;
        }

      }
      // Field item deltas are reset when an item is removed. This removes
      // items in descending order so that the deltas yet to be removed will
      // continue to exist.
      rsort($deltas_to_be_removed);
      foreach ($deltas_to_be_removed as $delta) {
        $field_interests->removeItem($delta);
      }
      $user->save();
    }
  }

  /**
   * Set user topic.
   */
  public function actOnSubmission(WebformSubmission $submission) {
    if ($submission->getElementData($this->getQuestionField()) === 'yes') {
      /** @var \Drupal\prompts\Plugin\Prompt\NewInterestingTopics $plugin_object */
      $this->updateUserTopics($submission->getOwner(), [$submission->getSourceEntity()->id()]);
    }
  }

}
