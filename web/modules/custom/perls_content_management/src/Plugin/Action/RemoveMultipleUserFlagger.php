<?php

namespace Drupal\perls_content_management\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\flag\FlagInterface;

/**
 * Remove more flags from multiple user.
 *
 * @Action(
 *   id = "unflag_multiple_user_to_content",
 *   label = @Translation("Remove mutiple user flag"),
 *   type = "node"
 * )
 */
class RemoveMultipleUserFlagger extends MultipleUserFlaggerBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $multiple = TRUE) {
    $form = parent::buildConfigurationForm($form, $form_state, $multiple);
    return $form;
  }

  /**
   * Remove flagging from multiple users.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The drupal flag object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object, this entity will be flagged.
   * @param array $users
   *   List of uid.
   */
  public function flagAction(FlagInterface $flag, EntityInterface $entity, array $users) {
    foreach ($users as $user) {
      $account = $this->userStorage->load($user['target_id']);
      if ($this->flagService->getFlagging($flag, $entity, $account)) {
        $this->flagService->unflag($flag, $entity, $account);
      }
      else {
        $this->messenger->addMessage($this->t("This user %user doesn't have %flag flag on content the %content.",
          [
            '%content' => $entity->label(),
            '%flag' => $flag->label(),
            '%user' => $account->label(),
          ]), MessengerInterface::TYPE_WARNING);
      }
    }
  }

}
