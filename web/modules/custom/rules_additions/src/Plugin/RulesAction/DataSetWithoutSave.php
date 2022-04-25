<?php

namespace Drupal\rules_additions\Plugin\RulesAction;

use Drupal\rules\Plugin\RulesAction\DataSet;

/**
 * Provides a 'Data set' action.
 *
 * @RulesAction(
 *   id = "rules_data_set_without_save",
 *   label = @Translation("Set a data value without saving context"),
 *   category = @Translation("Data"),
 *   context_definitions = {
 *     "data" = @ContextDefinition("any",
 *       label = @Translation("Data"),
 *       description = @Translation("Specifies the data to be modified using a data selector, e.g. 'node.author.name'.
 *       This is useful when you want to set a field value in pre-save hook."),
 *       allow_null = TRUE,
 *       assignment_restriction = "selector"
 *     ),
 *     "value" = @ContextDefinition("any",
 *       label = @Translation("Value"),
 *       description = @Translation("The new value to set for the specified data."),
 *       default_value = NULL,
 *       required = FALSE
 *     ),
 *   }
 * )
 */
class DataSetWithoutSave extends DataSet {

  /**
   * {@inheritdoc}
   */
  public function autoSaveContext() {
    return [];
  }

}
