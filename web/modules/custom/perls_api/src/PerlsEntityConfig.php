<?php

namespace Drupal\perls_api;

use Drupal\entity_normalization\EntityConfig;

/**
 * Contains a PerlsEntityConfig object which describe the normalization scheme.
 */
class PerlsEntityConfig extends EntityConfig {

  /**
   * {@inheritdoc}
   */
  public function getFields() {
    if (!isset($this->fields)) {
      $this->fields = [];
      if (isset($this->pluginDefinition['fields'])) {
        foreach ($this->pluginDefinition['fields'] as $fieldName => $fieldConfig) {
          if (isset($fieldConfig['type']) && $fieldConfig['type'] === 'inherited') {
            $this->fields[$fieldName] = new PerlsFieldConfig($fieldName, $fieldConfig);
          }
          else {
            $this->fields[$fieldName] = new FieldConfig($fieldName, $fieldConfig);
          }

        }
      }
    }
    return $this->fields;
  }

}
