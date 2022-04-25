<?php

namespace Drupal\react_comments\Plugin\rest\resource;

use Drupal\comment\CommentInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\react_comments\CommentFieldSettings;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\react_comments\Model\User;
use Drupal\react_comments\Model\Comment as CommentModel;
use Drupal\react_comments\Model\Comments as CommentsModel;
use Drupal\react_comments\Model\Response as ResponseModel;
use Drupal\react_comments\Model\Request as RequestModel;

/**
 * Provides comments for a given node resource.
 *
 * @RestResource(
 *   id = "comments",
 *   label = @Translation("Comments"),
 *   uri_paths = {
 *     "canonical" = "/react-comments/comments/{entity_id}",
 *     "create" = "/react-comments/comments/{entity_id}"
 *   }
 * )
 */
class Comments extends ResourceBase {

  public function get($entity_id = NULL) {
    $response = new ResponseModel();

    // @todo make this less specific, comments don't necessarily have to be on a node.
    $node = Node::load($entity_id);

    $cache_metadata = new CacheableMetadata();
    if ($node) {
      $cache_metadata->addCacheableDependency($node);
    }

    if (empty($entity_id)) {
      $response->setCode('invalid_nid');
    }
    else if (CommentFieldSettings::getCommentFieldStatus($entity_id) === 'hidden') {
      $response->setCode('comments_hidden');
      $res = (new ResourceResponse($response->model(), 403));
      $res->addCacheableDependency($cache_metadata);
      return $res;
    }
    else if (!$node) {
      $response->setCode('nid_not_found');
    }
    else {
      $comments = new CommentsModel();

      $show_unpublished = \Drupal::currentUser()->hasPermission('administer comments');

      if ($items = $comments->setEntityId($entity_id)->load($show_unpublished)) {
        $response->setData($items->model())
          ->setCode('success');
        
        // Since we're exposing properties about each user 
        // (to display as the comment author),
        // we need to invalidate a cached response if any of those users change.
        $cache_tags = array_map(function($comment) {
          return 'user:' . $comment['user']['id'];
        }, $items->getComments());
        $cache_metadata->addCacheTags($cache_tags);
      }
      else {
        $response->setCode('no_comments_found');
      }
    }

    $res = (new ResourceResponse($response->model()));
    $res->getCacheableMetadata()->addCacheContexts(['user.permissions']);

    // Since we're exposing field config settings (to determine whether or not anons should leave contact info) we need to add the appropriate cache tags.
    $res->getCacheableMetadata()->addCacheTags(CommentFieldSettings::getCommentFieldConfigCacheTags($entity_id));

    $res->getCacheableMetadata()->addCacheTags(['react_comment_list']);

    $res->addCacheableDependency($cache_metadata);
    return $res;
  }

  public function post($entity_id = NULL) {
    $response = new ResponseModel();
    $drupal_user = \Drupal::currentUser();

    if (empty($entity_id)) {
      $response->setCode('invalid_nid');
      return (new JsonResponse($response->model(), 404));
    }
    else if ((CommentFieldSettings::getCommentFieldStatus($entity_id) === 'closed') && !$drupal_user->hasPermission('administer comments')) {
      $response->setCode('comments_closed');
      return (new JsonResponse($response->model(), 403));
    }

    // @todo make this less specific, comments don't necessarily have to be on a node.
    if ($node = Node::load($entity_id)) {

      $request = (new RequestModel())->parseContentJson();
      $comment = new CommentModel();
      $current_user = new User($drupal_user);
      $response->setCode('not_authorized');

      if (is_numeric($current_user->getId()) && $drupal_user->hasPermission('post comments')) {
        $comment_body = $request->getJsonVal('comment');

        $skip_approval = $drupal_user->hasPermission('skip comment approval');

        if ($skip_approval) {
          $comment->setStatus( RC_COMMENT_PUBLISHED );
        }
        else {
          $comment->setStatus( RC_COMMENT_UNPUBLISHED );
        }

        if ($current_user->getId() === 0) {
          $comment_field_settings = CommentFieldSettings::getCommentFieldSettings($entity_id);

          // These two cases should never happen
          if (isset($comment_field_settings['anonymous']) && $comment_field_settings['anonymous'] === CommentInterface::ANONYMOUS_MUST_CONTACT && empty($request->getJsonVal('anon_email'))) {
            $response->setCode('anon_contact_required');
            return (new JsonResponse($response->model(), 400));
          }
          else if (isset($comment_field_settings['anonymous']) && $comment_field_settings['anonymous'] === CommentInterface::ANONYMOUS_MAYNOT_CONTACT && !empty($request->getJsonVal('anon_email'))) {
            $response->setCode('anon_contact_forbidden');
            return (new JsonResponse($response->model(), 400));
          }

          $comment->setName($request->getJsonVal('anon_name'));
          $comment->setEmail($request->getJsonVal('anon_email'));
        }

        $comment_field_name_and_type = $this->getCommentFieldNameAndType($node);

        $comment->setEntityId($entity_id)
          ->setUser($current_user)
          ->setFieldName($comment_field_name_and_type['name'])
          ->setCommentType($comment_field_name_and_type['type'])
          ->setComment($comment_body);
        $comment->setReplyId($request->getJsonVal('reply_comment_id'));
        $comment_id = $comment->save();
        if ($comment_id) {
          $response->setData($comment->setId($comment_id)->load()->model())
            ->setCode('queued_for_moderation');
        }
        if ($skip_approval) {
          $response->setCode('success');
        }
      }

      // Invalidate node cache.
      Cache::invalidateTags($node->getCacheTags());
    }
    else {
      $response->setCode('nid_not_found');
    }

    return (new JsonResponse($response->model()));
  }

  private function getCommentFieldNameAndType($node) {
    // Get the comment field name and type using in the current node
    $comment_field_name = 'comment';
    $comment_type = 'comment';

    $field_definitions = $node->getFieldDefinitions();

    foreach ($field_definitions as $definition) {
      // Get field definitions of a comment field
      if ($definition instanceof FieldConfig) {
        $type = $definition->get('field_type');

        if (isset($type) && $type === 'comment') {
          // Get comment field name from field definitions
          $comment_field_name_value = $definition->get('field_name');
          // Make sure this value is not null
          if (isset($comment_field_name)) {
            $comment_field_name = $comment_field_name_value;
          }

          // Get comment field type from field storage definitions
          $fsd = $definition->getFieldStorageDefinition();
          if (isset($fsd)) {
            $comment_type_value = $fsd->getSetting('comment_type');
            // Make sure this value is not null
            if (isset($comment_type_value)) {
              $comment_type = $comment_type_value;
            }
          }
          break;
        }
      }
    }

    return [
      'name' => $comment_field_name,
      'type' => $comment_type
    ];
  }
}
