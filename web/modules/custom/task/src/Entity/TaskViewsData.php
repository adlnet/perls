<?php

namespace Drupal\task\Entity;

use Drupal\Core\Entity\Sql\TableMappingInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\views\EntityViewsData;

/**
 * Provides Views data for task entities.
 */
class TaskViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  protected function mapFieldDefinition($table, $field_name, FieldDefinitionInterface $field_definition, TableMappingInterface $table_mapping, &$table_data) {
    // This was adapted from
    // https://www.drupal.org/project/drupal/issues/3098560.
    parent::mapFieldDefinition($table, $field_name, $field_definition, $table_mapping, $table_data);
    $field_column_mapping = $table_mapping->getColumnNames($field_name);
    foreach ($field_column_mapping as $schema_field_name) {
      if (isset($field_definition) && !$field_definition->isRequired()) {
        // Provides "Is empty (NULL)" and "Is not empty (NOT NULL)" operators.
        $table_data[$schema_field_name]['filter']['allow empty'] = TRUE;
      }
    }
  }

}
