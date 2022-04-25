<?php

namespace Drupal\perls_api;

/**
 * Extends FieldConfig that handle extra properties for inherited type.
 */
class PerlsFieldConfig extends FieldConfig implements PerlsFieldConfigInterface {

  /**
   * {@inheritdoc}
   */
  public function getParent() {
    return $this->definition['parent'] ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceField() {
    return $this->definition['reference_field'] ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getInheritedFieldName() {
    return $this->definition['inheritated_field'] ?: NULL;
  }

}
