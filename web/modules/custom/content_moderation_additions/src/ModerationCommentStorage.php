<?php

namespace Drupal\content_moderation_additions;

use Drupal\comment\Entity\Comment;
use Drupal\Core\Session\AccountInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Handles storing moderation comments.
 */
class ModerationCommentStorage implements ModerationCommentStorageInterface {
  use StringTranslationTrait;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * Constructs a new ModerationCommentStorage object.
   */
  public function __construct(ModerationInformationInterface $moderation_information) {
    $this->moderationInfo = $moderation_information;
  }

  /**
   * {@inheritdoc}
   */
  public function postComment(ContentEntityInterface $entity, AccountInterface $account, $message, $vid, $new_state = NULL) {
    $comment_message = $this->getModerationComment($entity, strip_tags($message), $new_state);

    if (empty($comment_message)) {
      return;
    }

    $comment = Comment::create([
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'field_name' => 'field_moderation_comments',
      'uid' => $account->id(),
      'comment_type' => 'moderation',
      'subject' => '',
      'comment_message' => $comment_message,
      'comment_revision' => $vid,
      'status' => 1,
      'langcode' => $entity->language()->getId(),
    ]);

    $comment->save();
  }

  /**
   * Prepares a moderation comment with additional metadata.
   *
   * This will append the original message information about
   * the moderation transition (e.g. from draft to review).
   */
  protected function getModerationComment(ContentEntityInterface $entity, $message, $new_state = NULL) {
    $transitionLabel = $this->getEntityModerationTransitionLabel($entity, $new_state);
    return $this->formatMessageWithEntityTransition($entity, $message, $transitionLabel);
  }

  /**
   * Formats the message entered by the user with the transition label.
   */
  protected function formatMessageWithEntityTransition(ContentEntityInterface $entity, $message, $transitionLabel) {
    if (!isset($transitionLabel)) {
      return $message;
    }

    if (empty($message)) {
      return $transitionLabel;
    }

    return $message . "\n" . $transitionLabel;
  }

  /**
   * Retrieves the transition label for this entity and the new state.
   *
   * This will return NULL if there is no notable transition.
   */
  private function getEntityModerationTransitionLabel(ContentEntityInterface $entity, $new_state = NULL) {
    if (!$new_state) {
      return NULL;
    }

    // Try to load the original revision.
    if (isset($entity->original)) {
      $original = $entity->original;
    }
    else {
      $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
      $original = $storage->loadRevision($entity->getLoadedRevisionId());
    }
    if (!$original) {
      return NULL;
    }

    $original_state = $original->moderation_state->value;
    if ($original_state == $new_state) {
      return NULL;
    }

    // If the language of the original entity doesn't match, it means the
    // current entity is a new translation. Adjusting the message accordingly.
    $translation_language = '';
    if ($entity->language()->getId() != $original->language()->getId()) {
      $translation_language = $entity->language()->getName();
    }

    return $this->getModerationTransitionLabel($entity, $original_state, $new_state, $translation_language);
  }

  /**
   * Prepares a translated label representing the transition between two states.
   */
  private function getModerationTransitionLabel(ContentEntityInterface $entity, $old_state, $new_state, $translation_language = '') {
    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);

    $new_state_label = $workflow->getTypePlugin()->getState($new_state)->label();
    // New translation.
    if ($translation_language) {
      return $this->t('Created a new translation in @language as @state', [
        '@language' => $translation_language,
        '@state' => $new_state_label,
      ]);
    }

    // Change in moderation state of the same translation.
    $old_state_label = $workflow->getTypePlugin()->getState($old_state)->label();
    return $this->t('Moved from @old_state to @new_state', [
      '@old_state' => $old_state_label,
      '@new_state' => $new_state_label,
    ]);
  }

}
