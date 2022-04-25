<?php

namespace Drupal\perls_goals;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\perls_api\UserStatistics;
use Drupal\user\UserInterface;

/**
 * Help to calculate and check the user's personal goals.
 */
class GoalCalculator {

  use StringTranslationTrait;

  /**
   * Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * User statistics helper service.
   *
   * @var \Drupal\perls_api\UserStatistics
   */
  protected $userStat;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * UserGoalNotificationHelper constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Drupal database connection.
   * @param \Drupal\perls_api\UserStatistics $user_stat
   *   User statistics helper service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    Connection $database,
    UserStatistics $user_stat,
    EntityTypeManagerInterface $entity_type_manager) {
    $this->database = $database;
    $this->userStat = $user_stat;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Collects those users who doesn't have active goals.
   *
   * @return array
   *   A list user uid who doesn't have active goals.
   */
  public function getUserWithoutGoals() {
    $query = $this->database->select('users', 'u')
      ->fields('u', ['uid']);
    $query->leftJoin('user__field_goal_monthly_course_comp', 'ufgmcc', 'u.uid = ufgmcc.entity_id');
    $query->isNull('field_goal_monthly_course_comp_value');
    $query->leftJoin('user__field_goal_weekly_completions', 'ufgwc', 'u.uid = ufgwc.entity_id');
    $query->isNull('field_goal_weekly_completions_value');
    $query->leftJoin('user__field_goal_weekly_views', 'ufgwv', 'u.uid = ufgwv.entity_id');
    $query->isNull('field_goal_weekly_views_value');

    $users_without_system_goals = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

    $query = $this->database->select('users', 'u')
      ->fields('u', ['uid']);
    $query->leftJoin('task_field_data', 'tfd', 'u.uid = tfd.user_id AND tfd.status = 1 AND tfd.completion_date IS NULL');
    $query->groupBy('u.uid');
    $query->having('COUNT(status) = 0');
    $users_without_custom_goals = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

    return array_intersect($users_without_system_goals, $users_without_custom_goals);
  }

  /**
   * Helps to decide the progress of goals.
   *
   * It goes through all user goals and it tries to decide which is the nearest
   * goal what user can reach. If all goal are reached it just send back an all
   * message otherwise a goal name and the difference between the current status
   * and the goal. If the progress hasn't started we send back NULL so we won't
   * send any notification.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   * @param array $field_list
   *   A field mapping with drupal field and the pair of it in the api.
   *
   * @return array|string|null
   *   The result of goal check, see the result above.
   */
  public function checkGoal(UserInterface $user, array $field_list) {
    $user_statistics = $this->userStat->getUserStatistics($user);
    $reached_goal_counter = 0;
    $closest_goal = NULL;
    $result = [];
    $set_goal_counter = 0;
    foreach ($field_list as $field) {
      if ($user->hasField($field['drupal_field']) && $user->get($field['drupal_field'])->getString() > 0) {
        $set_goal_counter++;
        if ((int) $user->get($field['drupal_field'])->getString() <= $user_statistics[$field['api_field']]) {
          $reached_goal_counter++;
        }
        elseif (is_null($closest_goal) || (int) $user_statistics[$field['api_field']] / (int) $user->get($field['drupal_field'])->getString() > $closest_goal) {
          $closest_goal = (int) $user_statistics[$field['api_field']] / (int) $user->get($field['drupal_field'])->getString();
          $result = [
            'score' => $user_statistics[$field['api_field']],
            'goal' => $user->get($field['drupal_field'])->getString(),
            'missing' => (int) $user->get($field['drupal_field'])->getString() - (int) $user_statistics[$field['api_field']],
            'goal_name' => $field['api_field'],
          ];
        }
      }
    }
    // All goal reached.
    if ($reached_goal_counter > 0 && $reached_goal_counter === $set_goal_counter) {
      return 'all';
    }
    elseif (!empty($result)) {
      return $result;
    }

    return NULL;
  }

  /**
   * Helps to decide if user has goals.
   *
   * It goes through all of the user's custom goals.
   * It will display the goal text if only one is open.
   * It will display multiple custom goals message if multiple are open.
   * It will return NULL if there are no open custom goals.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   *
   * @return string|null
   *   The result of goal check, see the result above.
   */
  public function checkCustomGoals(UserInterface $user) {
    $entityStorage = $this->entityTypeManager->getStorage('task');
    $query = $entityStorage->getQuery()
      ->condition('type', 'user_task')
      ->condition('user_id', $user->id())
      ->notExists('completion_date');
    $taskIds = $query->execute();
    $taskIdCount = count($taskIds);
    if ($taskIdCount == 0) {
      return NULL;
    }
    if ($taskIdCount == 1) {
      /** @var \Drupal\task\Entity\Task $task */
      $task = $entityStorage->load(reset($taskIds));
      return $task->name->value;
    }
    return $this->t("Time to check in on your goals. How are these %count goals going?",
      [
        "%count" => $taskIdCount,
      ]);
  }

  /**
   * Checks that a set of goal was achieved or not.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   * @param array $goal_list
   *   List of goals to check.(goal field setting)
   *
   * @return array
   *   A list of achieved goals, keyed by api name of the field.
   */
  public function checkAchievedGoals(UserInterface $user, array $goal_list) {
    $achieved_goals = [];
    $user_statistics = $this->userStat->getUserStatistics($user);
    foreach ($goal_list as $field) {
      // It doesn't make sense to send any notification if the progress is 0.
      if ($user->hasField($field['drupal_field']) &&
        $user->get($field['drupal_field'])->getString() > 0 &&
        $user_statistics[$field['api_field']] > 0) {
        if ((int) $user->get($field['drupal_field'])->getString() <= $user_statistics[$field['api_field']]) {
          $achieved_goals[$field['api_field']] = $field;
        }
      }
    }
    return $achieved_goals;
  }

}
