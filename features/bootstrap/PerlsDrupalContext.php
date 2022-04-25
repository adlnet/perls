<?php

use Behat\Behat\Context\SnippetAcceptingContext;

/**
 * Defines application features from the specific context.
 */
class PerlsDrupalContext extends ProjectBaseDrupalContext implements SnippetAcceptingContext {

  /**
   * Reset the recommendation engine graph.
   *
   * @Then I reset recommendation engine graph
   */
  public function iResetRecommendationEngineGraph() {
    $this->getSession()->visit($this->locatePath('admin/config/system/recommendation_engine/configure'));
    $page = $this->getSession()->getPage();
    $submit = $page->findButton("Reset Graph");
    if (empty($submit)) {
      throw new \Exception(sprintf("No Reset Graph button at %s", $this->getSession()->getCurrentUrl()));
    }
    // Reset Graph.
    $submit->click();
  }

  /**
   * Check the recommendation status of a given user.
   *
   * @Then :username should have recommendaton status :status
   */
  public function shouldHaveRecommendationStatus($username, $status) {
    $this->getSession()->visit($this->locatePath('admin/config/system/recommendation_engine/status'));
    $this->assertTextInTableRow($status, $username);
  }

  /**
   * Have a user flag a piece of content. The content must be
   * created by the test.
   *
   * @Then :username flags :content as :flag
   */
  public function userFlagsContent($username, $content, $flag) {
    // Load the content.
    foreach ($this->nodes as $node_object) {
      if ($node_object->title == $content) {
        $node = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
          'nid' => $node_object->nid,
        ]);
      }
    }
    if (empty($node)) {
      throw new \Exception(sprintf("Node with title '%s' not found", $content));
    }

    // Load the Flag.
    $flag_service = \Drupal::service('flag');
    $flag = $flag_service->getFlagById($flag);
    if ($flag === NULL) {
      throw new \Exception(sprintf("The '%s' could not be found", $flag));
    }

    // Load user.
    $user_object = $this->getUserManager()->getUser($username);
    $user = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties([
      'uid' => $user_object->uid,
    ]);
    if (empty($user)) {
      throw new \Exception(sprintf("User with name '%s' not found", $username));
    }
    // Flag Content.
    $flag_service->flag($flag, reset($node), reset($user));
  }

}
