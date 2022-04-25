<?php

namespace Drupal\content_moderation_additions\Plugin\Action;

/**
 * Publish a node.
 *
 * @Action(
 *   id = "content_publish",
 *   label = @Translation("Moved to Published"),
 *   type = "node"
 * )
 */
class Publish extends BaseModerationAction {

  /**
   * {@inheritdoc}
   */
  protected function getTargetStateId() {
    return 'published';
  }

}
