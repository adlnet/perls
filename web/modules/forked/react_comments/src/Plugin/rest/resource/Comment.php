<?php

namespace Drupal\react_comments\Plugin\rest\resource;

use Drupal\Core\Cache\Cache;
use Drupal\node\Entity\Node;
use Drupal\react_comments\CommentFieldSettings;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\react_comments\Model\User;
use Drupal\react_comments\Model\Comment as CommentModel;
use Drupal\react_comments\Model\Response as ResponseModel;
use Drupal\react_comments\Model\Request as RequestModel;

/**
 * Provides an individual comment resource.
 *
 * @RestResource(
 *   id = "comment",
 *   label = @Translation("Comment"),
 *   uri_paths = {
 *     "canonical" = "/react-comments/comment/{cid}",
 *     "https://www.drupal.org/link-relations/create" = "/react-comments/comment/{cid}"
 *   }
 * )
 */
class Comment extends ResourceBase {

  /**
   * Update comment custom status.
   */
  public function put($cid = NULL) {
    $request = (new RequestModel())->parseContentJson();
    $op = $request->getJsonVal('op');

    $response = new ResponseModel();
    $response->setCode('cid_not_found');

    if (empty($cid)) {
      $response->setCode('invalid_cid');
    }
    else {
      $current_user = new User(\Drupal::currentUser());
      if (!$current_user->getId()) {
        $response->setCode('not_authorized');
      }
      else {

        $comment = (new CommentModel())->setId($cid);

        switch ($op) {
          case 'flag':
            if ($comment->load()) {
              if ($comment->getStatus() != RC_COMMENT_FLAGGED) {
                $comment->setStatus( RC_COMMENT_FLAGGED )->update();
                $response->setData($comment->model())
                  ->setCode('success');
              }
              else {
                $response->setData($comment->model())
                  ->setCode('already_flagged');
              }
            }
            break;
          case 'publish':
          case 'unpublish':
            if (!$current_user->hasPermission('administer comments')) break;

            if ($comment->load()) {
              $op = ($op === 'publish') ? RC_COMMENT_PUBLISHED : RC_COMMENT_UNPUBLISHED;
              if ($comment->getStatus() != $op) {
                $comment->setStatus($op)->update();
                $response->setData($comment->model())
                  ->setCode('success');
              }
            }
        }
      }
    }

    return (new JsonResponse($response->model()));
  }

  /**
   * Update comment.
   */
  public function patch($cid = NULL) {
    $request = (new RequestModel())->parseContentJson();
    $response = new ResponseModel();
    if (empty($cid)) {
      $response->setCode('invalid_cid');
    }
    else {
      $current_user = \Drupal::currentUser();

      if (!$current_user->id()) {
        $response->setCode('not_authorized');
      }

      $comment = new CommentModel();
      $response->setCode('cid_not_found');
      if ($comment->setId($cid)->load()) {
        if (!$current_user->hasPermission('administer comments') && (CommentFieldSettings::getCommentFieldStatus($comment->getEntityId()) === 'closed')) {
          $response->setCode('comments_closed');
          return new JsonResponse($response->model(), 403);
        }
        else if (($current_user->id() !== (string) $comment->getUser()->getId()) && !$current_user->hasPermission('administer comments')) {
          $response->setCode('not_authorized');
          return new JsonResponse($response->model(), 403);
        }
        else if ($comment->getStatus() !== 0 || $current_user->hasPermission('administer comments')) {
          $comment_body = $request->getJsonVal('comment');
          $comment->setComment($comment_body)->update();
          $response->setData($comment->model())
            ->setCode('success');
        }
        else {
          $response->setCode('comment_deleted');
        }
      }
    }
    $response = new JsonResponse($response->model());
    return $response;
  }

  /**
   * Delete comment.
   */
  public function delete($cid = NULL) {
    $response = new ResponseModel();
    if (empty($cid)) {
      $response->setCode('invalid_cid');
    }
    else {
      $current_user = new User(\Drupal::currentUser());
      if (!$current_user->getId()) {
        $response->setCode('not_authorized');
      }
      else {
        $comment = new CommentModel();
        $response->setCode('cid_not_found');
        if ($item = $comment->setId($cid)->load()) {
          if (!$current_user->hasPermission('administer comments') && (CommentFieldSettings::getCommentFieldStatus($comment->getEntityId()) === 'closed')) {
            $response->setCode('comments_closed');
            return new JsonResponse($response->model(), 403);
          }
          else if ($item->getStatus() != RC_COMMENT_DELETED) {
            $item->setUser($current_user)->delete();
            if ($node = Node::load($comment->getEntityId())) {
              Cache::invalidateTags($node->getCacheTags());
            }
            $response->setData($item->model())
              ->setCode('success');
          }
          else {
            $response->setCode('already_deleted');
          }
        }
      }
    }
    return (new JsonResponse());
  }

}
