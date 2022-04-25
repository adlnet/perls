<?php

namespace Drupal\react_comments;

use Drupal\node\Entity\Node;

/**
 * Provides methods to check drupal comment field settings (open/closed/hidden etc)
 */
class CommentFieldSettings {

  private static function getCommentField($nid) {
    $comment_fields = &drupal_static(__FUNCTION__);

    if (!isset($comment_fields[$nid])) {
      $node = Node::load($nid);

      foreach ($node->getFieldDefinitions() as $field_name => $definition) {
        $field = $node->get($field_name);
        if (is_a($field, 'Drupal\comment\CommentFieldItemList')) {
          // We're assuming there's only one comment field per node. Seems sensible...
          $comment_fields[$nid] = $field;
          break;
        }
      }
    }

    return $comment_fields[$nid];
  }

  public static function getCommentFieldStatus($nid) {
    $status = null;

    $field = self::getCommentField($nid);
    $status = $field->status;

    if (is_null($status)) {
      // No comment field found.
      return false;
    }
    else if (($status === '1') || ($status === 1)) {
      return 'closed';
    }
    else if (($status === '2') || ($status === 2)) {
      return 'open';
    }
    else {
      // shouldn't need this as comment module handles hidden comments already
      return 'hidden';
    }
  }

  public static function getCommentFieldSettings($nid) {
    $field = self::getCommentField($nid);

    return $field ? $field->getSettings() : null;
  }

  public static function getCommentFieldConfigCacheTags($nid) {
    $field = self::getCommentField($nid);

    return $field->getFieldDefinition()->getCacheTagsToInvalidate();
  }
}
