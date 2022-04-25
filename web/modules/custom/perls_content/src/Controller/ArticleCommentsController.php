<?php

namespace Drupal\perls_content\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;

/**
 * Article Comments page related controllers.
 */
class ArticleCommentsController extends ControllerBase {

  /**
   * Renders Learn Article node in "discussion" view mode.
   */
  public function render(Node $node) {
    $view_builder = $this->entityTypeManager()->getViewBuilder($node->getEntityTypeId());
    return $view_builder->view($node, 'discussion');
  }

  /**
   * Custom access control.
   */
  public function access(AccountInterface $account, Node $node) {
    if ($node->bundle() !== 'learn_article') {
      return AccessResult::forbidden();
    }
    return $node->access('view', $account, TRUE);
  }

  /**
   * Returns node title.
   */
  public function getTitle(Node $node) {
    return $node->getTitle();
  }

}
