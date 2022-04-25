<?php

namespace Drupal\perls_learner_browse;

use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;

/**
 * Service that can provide content suggestions for users.
 */
class ContentSuggestor {

  const DESIRED_ROWS = 5;
  const NODES_PER_ROW = 10;

  /**
   * Retrieves a collection of relevant content for the specified user.
   *
   * "Relevant" content is currently defined as:
   *  1. The user's recent history.
   *  2. Topics the user has expressed interest in.
   *
   * If there is not enough content, then the collection is augmented with:
   *  3. Topics with the most content.
   *  4. Popular and new content.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return ViewCollection
   *   A collection of relevant content for the specified user.
   */
  public function getRelevantContent(AccountInterface $account): ViewCollection {
    $collection = new ViewCollection(self::NODES_PER_ROW);

    // Always add the user's history.
    $collection->addViewById('history');

    // Add topics that may be relevant to the user.
    $this->addRelevantTopics($collection, $account);

    if ($collection->rowCount() < self::DESIRED_ROWS) {
      $this->augmentSuggestions($collection, $account);
    }

    return $collection;
  }

  /**
   * Adds topics relevant to the specified user to the view collection.
   *
   * Relevant topics are those the user has expressed interest in.
   * If the user hasn't expressed interest in enough topics, then
   * topics with the most content is added.
   *
   * @param ViewCollection $collection
   *   A collection of relevant content for the user.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   */
  protected function addRelevantTopics(ViewCollection $collection, AccountInterface $account) {
    $tids = [];
    $limit = self::DESIRED_ROWS - $collection->rowCount();

    foreach ($this->getInterests($account) as $term) {
      $tids[] = $term->id();
    }

    if (count($tids) < $limit) {
      foreach ($this->getPopularTopics($limit - count($tids)) as $term) {
        $tids[] = $term->id();
      }
    }

    $tids = array_unique($tids);

    foreach ($tids as $tid) {
      $collection->addViewById('taxonomy_term', [$tid], 'embed_1');
    }
  }

  /**
   * Augments the view collection with additional content.
   *
   * A view collection is only augmented if there hasn't been enough
   * relevant content identifed by observing the user's history and interests.
   *
   * @param ViewCollection $collection
   *   A collection of relevant content for the user.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   */
  protected function augmentSuggestions(ViewCollection $collection, AccountInterface $account) {
    $collection->addViewById('trending_content', [], 'embed_1');
    $collection->addViewById('content_recent', [], 'embed_1');
  }

  /**
   * Retrieves the specified user's interests (topics).
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   An array of terms that the user has expressed interest in.
   */
  protected function getInterests(AccountInterface $account) {
    $user = User::load($account->id());
    $interests = $user->field_interests->referencedEntities();
    return $interests;
  }

  /**
   * Retrieves popular topics in the corpus.
   *
   * Popular topics is currently defined as topics with the most content.
   *
   * @param int $limit
   *   The maximum number of topics to return.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   An array of terms.
   */
  protected function getPopularTopics($limit = 5) {
    if ($limit === 0) {
      return [];
    }

    $query = \Drupal::database()->select('taxonomy_index', 'ti')
      ->fields('ti', ['tid'])
      ->fields('ttd', ['vid'])
      ->condition('ttd.vid', 'category')
      ->groupBy('ti.tid, ttd.vid')
      ->range(0, $limit);
    $query->innerJoin('taxonomy_term_data', 'ttd', 'ttd.tid=ti.tid');
    $query->addExpression('count(nid)', 'node_count');
    $query->orderBy('node_count', 'DESC');

    $tids = $query->execute()->fetchCol('tid');
    return \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($tids);
  }

}
