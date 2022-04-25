<?php

namespace Drupal\xapi\Plugin\Field\FieldFormatter;

use Drupal\views_field_formatter\Plugin\Field\FieldFormatter\ViewsFieldFormatter;

/**
 * Class ViewsFieldFormatter.
 *
 * @FieldFormatter(
 *  id = "xapi_content_views_field_formatter",
 *  label = @Translation("xAPI Content View"),
 *  weight = 100,
 *  field_types = {
 *   "field_xapi_content_file_item"
 *   }
 * )
 */
class XapiContentViewsFieldFormatter extends ViewsFieldFormatter {
}
