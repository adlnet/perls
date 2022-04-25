<?php

namespace Drupal\perls_learner_browse\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Enables learners to browse content in the corpus.
 */
class BrowseController extends ControllerBase {

  /**
   * Displays content relevant to the current user.
   *
   * Instead of showing the user everything in the corpus (which may be a lot),
   * we attempt to intelligently select content that is relevant to the user.
   *
   * @see \Drupal\perls_learner_browse\ContentSuggestor
   *
   * @return array
   *   A renderable array containing relevant content for the current user.
   */
  public function browse() {
    $collection = \Drupal::service('perls_learner_browse.content_suggestor')->getRelevantContent($this->currentUser());
    return $collection->build();
  }

  /**
   * Displays content users are folliwng.
   *
   * Show users recent content that is tagged with a term
   * the user is following.
   *
   * @see \Drupal\perls_learner_browse\FollowedContent.php
   *
   * @return array
   *   A renderable array containing content followed by the current user.
   */
  public function following() {
    $collection = \Drupal::service('perls_learner_browse.followed_content')->getFollowedContent($this->currentUser());
    if ($collection->rowCount() === 0) {
      return [
        'message' => [
          '#markup' => t("You're not following any #tags yet. You can find more content in your @recommendations_link (and maybe find some tags to follow).", [
            '@recommendations_link' => Link::fromTextAndUrl(t('recommendations'), Url::fromRoute('view.recommended_content.learner_page'))->toString(),
          ]),
        ],
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }
    return $collection->build();
  }

}
