<?php

namespace Drupal\perls_adaptive_content\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;

/**
 * Defines the 'adaptive content' entity field type.
 *
 * @FieldType(
 *   id = "adaptive_content_field",
 *   label = @Translation("Adaptive Content"),
 *   description = @Translation("A field containing a reference to adaptive content plugin."),
 *   category = @Translation("Text"),
 *   default_widget = "adaptive_content_widget",
 *   default_formatter = "string"
 * )
 */
class AdaptiveContentType extends StringItem {}
