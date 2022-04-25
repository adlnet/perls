<?php

namespace Drupal\veracity_vql\Plugin\VqlPreProcess;

use Drupal\veracity_vql\Plugin\VqlPreProcessBase;

/**
 * Base implementation of a pre-process plugin to filter results.
 */
abstract class FilterBase extends VqlPreProcessBase {

  /**
   * Retrieve a nested, associative array containing filter values.
   *
   * @return array
   *   Filter values.
   */
  abstract public function getFilter(): array;

  /**
   * {@inheritdoc}
   */
  public function alterQuery(array &$query) {
    if (!isset($query['filter'])) {
      $query['filter'] = [];
    }

    $query['filter'] += $this->parseFilter($this->getFilter());
  }

  /**
   * Flattens the filter array into a filter query.
   *
   * Stops flattening when it encounters a key starting with a $.
   *
   * @param array $filter
   *   The filter.
   *
   * @return array
   *   The flattened filter query.
   */
  protected function parseFilter(array $filter): array {
    $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($filter));
    $result = [];

    foreach ($iterator as $value) {
      $keys = [];
      foreach (range(0, $iterator->getDepth()) as $depth) {
        $key = $iterator->getSubIterator($depth)->key();
        if (substr($key, 0, 1) === '$') {
          $value = [$key => $iterator->getSubIterator($depth)->current()];
          break;
        }
        $keys[] = $key;
      }

      if (empty($keys)) {
        $result += $value;
      }
      else {
        $result[implode('.', $keys)] = $value;
      }
    }

    return $result;
  }

}
