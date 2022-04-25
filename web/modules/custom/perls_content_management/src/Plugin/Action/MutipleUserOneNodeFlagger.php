<?php

namespace Drupal\perls_content_management\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\flag\FlagInterface;

/**
 * Adds more flags to multiple user.
 *
 * @Action(
 *   id = "flag_multiple_user_one_content",
 *   label = @Translation("Flag Content (same details for all nodes)"),
 *   type = "node"
 * )
 */
class MutipleUserOneNodeFlagger extends MultipleUserFlaggerBase {

  /**
   * {@inheritdoc}
   */
  public function execute($node = NULL) {
    $config = $this->configuration['multiple_flags'];
    foreach ($config['flags'] as $flag_name) {
      $flag = $this->flagService->getFlagById($flag_name);
      $this->flagAction($flag, $node, $config['users']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $multiple = TRUE) {
    $form = parent::buildConfigurationForm($form, $form_state, FALSE);
    return $form;
  }

  /**
   * Create flagging for multiple users.
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
      $flagging = $this->flagService->getFlagging($flag, $entity, $account);
      if (isset($flagging)) {
        $this->messenger->addMessage($this->t("This user %user has this flag on the %content content.", [
          '%content' => $entity->label(),
          '%user' => $account->label(),
        ]), MessengerInterface::TYPE_WARNING);
      }
      else {
        $this->flagService->flag($flag, $entity, $account);
      }
    }
  }

}
