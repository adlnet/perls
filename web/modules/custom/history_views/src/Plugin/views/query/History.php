<?php

namespace Drupal\history_views\Plugin\views\query;

use Drupal\views\Plugin\views\query\QueryPluginBase;

/**
 * History views query plugin which expose the user history data to views.
 *
 * @ViewsQuery(
 *   id = "history",
 *   title = @Translation("History"),
 *   help = @Translation("User data from the history table.")
 * )
 */
class History extends QueryPluginBase {
}
