<?php

namespace Drupal\task\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the task type entity.
 *
 * @ConfigEntityType(
 *   id = "task_type",
 *   label = @Translation("task type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\task\TaskTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\task\Form\TaskTypeForm",
 *       "edit" = "Drupal\task\Form\TaskTypeForm",
 *       "delete" = "Drupal\task\Form\TaskTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\task\TaskTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "type",
 *   admin_permission = "administer task types",
 *   bundle_of = "task",
 *   entity_keys = {
 *     "id" = "type",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "type",
 *     "label",
 *     "description",
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/task_type/{task_type}/edit",
 *     "delete-form" = "/admin/structure/task_type/{task_type}/delete",
 *     "collection" = "/admin/structure/task_type"
 *   }
 * )
 */
class TaskType extends ConfigEntityBundleBase implements TaskTypeInterface {

  /**
   * The task type ID.
   *
   * @var string
   */
  protected $type;

  /**
   * The task type label.
   *
   * @var string
   */
  protected $label;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return isset($this->type) ? $this->type : NULL;
  }

}
