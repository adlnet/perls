<?php

namespace Drupal\perls_goals;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Helper service to manage the goals.
 */
class GoalHelper {

  /**
   * Drupal field config storage.
   *
   * @var \Drupal\field\FieldConfigStorage
   */
  protected $fieldConfig;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  private $configFactory;

  /**
   * GoalHelper constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->fieldConfig = $entity_type_manager->getStorage('field_config');
    $this->configFactory = $config_factory;
  }

  /**
   * Gives back the goal field list.
   *
   * @return array
   *   The goal field list.
   */
  public function getGoalFieldList() {
    return $this->configFactory->get('perls_goals.settings')->get('fields.field_values');
  }

  /**
   * Load drupal goal field config.
   *
   * @param string $goal_name
   *   The name of the goal field(api name)
   *
   * @return \Drupal\field\Entity\FieldConfig|null
   *   Drupal field config otherwise NULL.
   */
  public function loadGoalField($goal_name) {
    $goal_fields = $this->getGoalFieldList();
    foreach ($goal_fields as $field) {
      if ($field['api_field'] === $goal_name) {
        $field_config_id = sprintf('user.user.%s', $field['drupal_field']);
        return $this->fieldConfig->load($field_config_id);
      }
    }

    return NULL;
  }

  /**
   * Gives back the raw goal field config.
   *
   * @param string $goal_name
   *   The name of the goal field(api name).
   *
   * @return array
   *   The field config as you set on settings form.
   *   return [
   *     drupal_field: field_goal_monthly_course_comp
   *     api_field: completed_count_course_month
   *     type: integer
   *     time_frame: month
   *     goal_type: completed
   *   ]
   */
  public function getGoalFieldData($goal_name) {
    $goal_fields = $this->getGoalFieldList();
    foreach ($goal_fields as $field) {
      if ($field['api_field'] === $goal_name) {
        return $field;
      }
    }

    return NULL;
  }

  /**
   * Gives back a goal with specific type.
   *
   * @param string $goal_type
   *   The goal type name. Currently the system knows completed and viewed type.
   *
   * @return array
   *   A list of goal field which match with a specific type of goal.
   *   [
   *     [
   *       drupal_field: field_goal_monthly_course_comp
   *       api_field: completed_count_course_month
   *       type: integer
   *       time_frame: month
   *       goal_type: completed
   *     ]
   *     [...]
   *   ]
   */
  public function getGoalsByType(string $goal_type): array {
    $goal_fields = $this->getGoalFieldList();
    $goal_list = [];
    foreach ($goal_fields as $field) {
      if ($field['goal_type'] === $goal_type) {
        $goal_list[] = $field;
      }
    }
    return $goal_list;
  }

  /**
   * Gives back a goal with specific type.
   *
   * @param string $goal_type
   *   The goal storage type. Currently the system knows avg and integer types.
   *
   * @return array
   *   A list of goal field which match with a specific type of goal.
   *   [
   *     [
   *       drupal_field: field_goal_monthly_course_comp
   *       api_field: completed_count_course_month
   *       type: integer
   *       time_frame: month
   *       goal_type: completed
   *     ]
   *     [...]
   *   ]
   */
  public function getGoalsByStoredValueType(string $goal_type): array {
    $goal_fields = $this->getGoalFieldList();
    $goal_list = [];
    foreach ($goal_fields as $field) {
      if ($field['stored_value_type'] === $goal_type) {
        $goal_list[] = $field;
      }
    }
    return $goal_list;
  }

}
