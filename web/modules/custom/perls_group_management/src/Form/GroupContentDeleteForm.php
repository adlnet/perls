<?php

namespace Drupal\perls_group_management\Form;

use Drupal\group\Entity\Form\GroupContentDeleteForm as ContribGroupContentDeleteForm;

/**
 * Customizes the confirmation form when removing content from a group.
 */
class GroupContentDeleteForm extends ContribGroupContentDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Removing %content', ['%content' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Are you sure you want to remove %content from %group?', [
      '%content' => $this->entity->label(),
      '%group' => $this->entity->getGroup()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Remove');
  }

}
