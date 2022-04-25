<?php

namespace Drupal\notifications_ui_additions\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\notifications\Plugin\Action\SendPushNotification as OriginalSendNotification;

/**
 * Custom form for creating a new notification.
 */
class SendNotification extends OriginalSendNotification {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Tests cannot be directly referenced.
    $types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
    unset($types['test']);

    $form['related_item'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Related content'),
      '#description' => $this->t('The recipient will be taken directly to this item when they open the notification.'),
      '#target_type' => 'node',
      '#selection_handler' => 'default:published_node',
      '#selection_settings' => [
        'target_bundles' => array_keys($types),
      ],
      '#validate_reference' => TRUE,
      '#placeholder' => $this->t('Optionally, start typing the name of a content item to include with the notification'),
      '#weight' => 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!empty($form_state->getValue('related_item'))) {
      $storage = \Drupal::entityTypeManager()->getStorage($form['related_item']['#target_type']);
      $entity = $storage->load($form_state->getValue('related_item'));
      $this->configuration['related_item'] = $entity;
      \Drupal::service('notifications_ui_additions.default')->addRelatedItem($this->messageEntity, $entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getRecipients(EntityInterface $entity): array {
    $recipients = parent::getRecipients($entity);
    if (!empty($this->configuration['related_item'])) {
      $related_entity = $this->configuration['related_item'];

      if ($related_entity) {
        $recipients = array_filter($recipients,
          function ($recipient) use ($related_entity) {
            $access = $related_entity->access('view', $recipient);
            if (!$access) {
              \Drupal::messenger()
                ->addWarning($this->t('Unable to send message to %recipient because they do not have access to %content',
                  [
                    '%recipient' => $recipient->label(),
                    '%content' => $related_entity->label(),
                  ]));
            }

            return $access;
          });
      }
    }

    return $recipients;
  }

}
