<?php

namespace Drupal\perls_learner\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides node comment statistics.
 */
class NodePublicStatsController extends ControllerBase {
  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;


  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * NodePublicStatsController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, Connection $connection) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('database')
    );
  }

  /**
   * Send comment stats for this node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response containing comment count.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function renderStats(NodeInterface $node) {
    $response = new AjaxResponse();
    // Proceed with comments stats if comments are open for current node.
    if ($node->hasField('field_comments') && !empty($node->get('field_comments'))) {
      // Node comment configurations to check the node comment status.
      if (perls_learner_comments_enabled($node)) {
        $stats = $this->getStats($node->id());
        // Render command stats only if the comment count is 1 or more.
        if ($stats) {
          $button = 'article.c-node--full--learn-article .view-comments';
          $statsSelector = "$button  span.comment-count";

          // Remove the comment count if added previously.
          $response->addCommand(new RemoveCommand($statsSelector));
          // Add a class for button's styling if comment stats are present.
          $response->addCommand(new InvokeCommand($button, 'addClass', ['stats-attached']));
          // Insert comment count.
          $response->addCommand(new AppendCommand($button, $this->t('<span class ="comment-count"> (@comment_count)</span>', ['@comment_count' => $stats])));
          return $response;
        }
      }
    }
    return $response;
  }

  /**
   * Get the comment statistics.
   *
   * @param int $nid
   *   Node entity.
   *
   * @return int
   *   Comment count.
   */
  protected function getStats(int $nid) {
    // Using `react_comments_status` table since the deleted comments recorded
    // by 'react_comments' module using status column in the db table.
    $query = $this->connection->select('comment_field_data', 'c')
      ->fields('c', ['cid'])
      ->fields('node_field_data', ['nid'])
      ->condition('c.entity_id', $nid);
    $query->innerJoin('node_field_data', 'nfd', "c.entity_id = nfd.nid AND c.entity_type = 'node'");

    // Count only published comments.
    $query->innerJoin('react_comments_status', 'r', 'c.cid = r.cid');
    $query->condition('r.status', 1);

    return $query->countQuery()->execute()->fetchField();
  }

}
