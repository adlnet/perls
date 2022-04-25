<?php

namespace Drupal\prompts\Plugin\Block;

/**
 * Provides prompt block.
 *
 * @Block(
 *   id = "prompt_block",
 *   admin_label = @Translation("Prompt block"),
 *   category = @Translation("Prompt"),
 * )
 */
class PromptBlock extends PromptBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return $this->prompt->generatePromptBlock();
  }

}
