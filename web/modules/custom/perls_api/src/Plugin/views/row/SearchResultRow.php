<?php

namespace Drupal\perls_api\Plugin\views\row;

use Drupal\views\Plugin\views\row\RowPluginBase;

/**
 * Views row plugin which returns the original entity for a search result.
 *
 * @ingroup views_row_plugins
 *
 * @ViewsRow(
 *   id = "search_entity",
 *   title = @Translation("Search Result Entity"),
 *   help = @Translation("Uses the original entity for the result."),
 *   display_types = {"data"},
 *   base = {"search_api_index_content_search"}
 * )
 */
class SearchResultRow extends RowPluginBase {
  /**
   * {@inheritdoc}
   */
  protected $usesOptions = FALSE;

  /**
   * {@inheritdoc}
   */
  public function render($row) {
    return $row->_item->getOriginalObject()->getValue();
  }

}
