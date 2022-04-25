<?php

namespace Drupal\perls_learner_browse;

use Drupal\Core\Session\AccountInterface;

/**
 * Service that can provide content suggestions for users.
 */
class FollowedContent {

  const NODES_PER_ROW = 10;

  /**
   * Retrieves a collection of content tagged and groupped by followed terms.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return ViewCollection
   *   A collection of followed content for the specified user.
   */
  public function getFollowedContent(AccountInterface $account): ViewCollection {
    $collection = new ViewCollection(self::NODES_PER_ROW, FALSE);
    // Check if a user is following any tags.
    $followed_tags = $this->getFollowedTags($account);
    $followed_tags = $this->orderTags($followed_tags);
    // Add topics that may be relevant to the user.
    foreach ($followed_tags as $tag) {
      $collection->addViewById('taxonomy_term', [$tag], 'embed_1');
    }

    return $collection;
  }

  /**
   * Retrieves the specified user's followed tags.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   An array of terms that the user is following.
   */
  protected function getFollowedTags(AccountInterface $account) {
    $flaggings = \Drupal::entityTypeManager()->getStorage('flagging')->loadByProperties(
      [
        'flag_id' => 'following',
        'uid' => $account->id(),
        'entity_type' => 'taxonomy_term',
      ]
    );
    $tags = [];
    foreach ($flaggings as $flagging) {
      $tags[] = $flagging->getFlaggableId();
    }
    return $tags;
  }

  /**
   * Order tag ids based on when content was last added to them.
   *
   * @param array $tag_ids
   *   The tag ids to order.
   *
   * @return array
   *   The order tag ids.
   */
  protected function orderTags(array $tag_ids) {
    if (empty($tag_ids)) {
      return $tag_ids;
    }
    $query = \Drupal::database()->select('taxonomy_index', 'ti')
      ->fields('ti', ['tid'])
      ->groupBy('tid')
      ->condition('tid', $tag_ids, 'IN');

    $query->addExpression('max(created)', 'tag_added');
    $query->orderBy('tag_added', 'DESC');
    $ordered_tags = $query->execute()->fetchCol('tid');

    // Some tags are empty and do not appear in this list.
    $empty_tags = array_diff($tag_ids, $ordered_tags);
    return array_merge($ordered_tags, $empty_tags);
  }

}
