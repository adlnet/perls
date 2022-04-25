<?php

namespace Drupal\content_moderation_additions\Plugin\Action;

/**
 * Find and replace strings across all fields and paragraphs.
 *
 * @Action(
 *   id = "content_archive",
 *   label = @Translation("Move to Archived"),
 *   type = "node"
 * )
 */
class Archive extends BaseModerationAction {

  /**
   * {@inheritdoc}
   */
  protected function getTargetStateId() {
    return 'archived';
  }

}
