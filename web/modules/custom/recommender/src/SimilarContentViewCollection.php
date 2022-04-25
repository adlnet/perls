<?php

namespace Drupal\recommender;

use Drupal\tools\ViewCollection;

/**
 * A collection of views.
 */
class SimilarContentViewCollection extends ViewCollection {

  /**
   * Returns the entities from the result of each view in the collection.
   *
   * Results are grouped in associative arrays by the view providing the result.
   *
   * The output of this method is cached.
   *
   * @return array
   *   An array suitable for serialization with the entities from each view.
   *
   *   The array contains associative arrays with the following keys:
   *    * 'name': The name of the group.
   *    * 'url': An optional URL to retrieve more content.
   *    * 'content': An array of entities in the group.
   */
  public function groupedResults() {
    $output = [];

    foreach ($this->views as $view) {
      if ($this->hidesEmptyViews && count($view->result) === 0) {
        continue;
      }
      if (empty($view->executed)) {
        continue;
      }

      foreach ($view->result as $row_index => $row) {
        $score = $row->_item->getScore();
        $node = $row->_object->getEntity();
        // We keep the top score for any given node.
        if (
            isset($output[$node->id()])
            && $score < $output[$node->id()]['score']) {
          continue;
        }
        $output[$node->id()]['score'] = $score;
        $output[$node->id()]['node'] = $node;
      }
    }

    return $output;
  }

}
