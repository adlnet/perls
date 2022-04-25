<?php

namespace Drupal\perls_api;

/**
 * Extends the FieldConfigInterface that we can handle inherited type.
 */
interface PerlsFieldConfigInterface extends FieldConfigInterface {

  /**
   * Returns with the parent bundle name.
   *
   * @return string|null
   *   The parent bundle name.
   */
  public function getParent();

  /**
   * Return with the reference field name.
   *
   * This field creates the connection between the parent and the entity itself.
   *
   * @return string|null
   *   The reference field name which belongs to parent bundle.
   */
  public function getReferenceField();

  /**
   * The normalizer will normalize this field which belongs to parent entity.
   *
   * @return string|null
   *   The field_name which will be normalized.
   */
  public function getInheritedFieldName();

}
