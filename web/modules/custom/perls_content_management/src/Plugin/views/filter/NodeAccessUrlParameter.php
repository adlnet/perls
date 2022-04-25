<?php

namespace Drupal\perls_content_management\Plugin\views\filter;

use Drupal\Core\Database\Query\Condition;
use Drupal\node\Plugin\views\filter\Access;
use Drupal\user\Entity\User;

/**
 * Filter by node_access for a user from url.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("node_access_url")
 */
class NodeAccessUrlParameter extends Access {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $url_param = $this->view->args;
    $account = $this->view->getUser();
    if (isset($url_param[0]) && is_numeric($url_param[0])) {
      $account = User::load($url_param[0]);
    }

    if ($account && !$account->hasPermission('bypass node access')) {
      $table = $this->ensureMyTable();
      $grants = new Condition('OR');
      foreach (node_access_grants('view', $account) as $realm => $gids) {
        foreach ($gids as $gid) {
          $grants->condition((new Condition('AND'))
            ->condition($table . '.gid', $gid)
            ->condition($table . '.realm', $realm)
          );
        }
      }

      $this->query->addWhere('AND', $grants);
      $this->query->addWhere('AND', $table . '.grant_view', 1, '>=');
    }
  }

}
