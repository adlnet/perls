<?php

namespace Drupal\notifications\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views_bulk_operations\Form\ConfirmAction;
use Drupal\Core\Url;

/**
 * Default action execution confirmation form.
 */
class ConfirmSendPushNotificationAction extends ConfirmAction {
  use ViewsBulkOperationsFormTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'notifications_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $view_id = NULL, $display_id = NULL) {
    $form = parent::buildForm($form, $form_state, $view_id, $display_id);
    $form_data = $this->getFormData($view_id, $display_id);

    // When the form is loaded incorrectly, the message data is empty.
    // E.g. when user goes back to the form after submitting it.
    if (empty($form_data['configuration']['messageEntityId'])) {
      $url = Url::fromRoute('view.send_notifications.page_1')->toString();
      $markup = $this->t(
        'No message configured. <a href="@url">Go back</a> and try again.',
        ['@url' => $url]
      );
      $form['error'] = [
        '#type' => 'markup',
        '#markup' => $markup,
      ];
      return $form;
    }

    $messageEntity = \Drupal::entityTypeManager()->getStorage('push_notification')->load($form_data['configuration']['messageEntityId']);
    $message = $messageEntity;

    unset($form['list']);

    $form['#title'] = $this->t('Sending %title to @recipients', [
      '%title' => $message->label(),
      '@recipients' => $this->getSelectionSummary($form_data),
    ]);

    if (empty($message->send_time->value)) {
      // Message will be sent _now_.
      $form['confirmation'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('This will be sent to @recipients <strong>immediately</strong>; double-check it for any typos or errors.', [
          '@recipients' => $this->getSelectionSummary($form_data),
        ]),
      ];

      $form['actions']['submit']['#value'] = $this->t('Send Now');
    }
    else {
      // Message will be queued for later.
      $form['confirmation'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t("This will be sent to @recipients on @when. You'll be able to edit it later.", [
          '@recipients' => $this->getSelectionSummary($form_data),
          '@when' => \Drupal::service('date.formatter')->format($message->send_time->value, 'medium'),
        ]),
      ];

      $form['actions']['submit']['#value'] = $this->t('Schedule Message');
    }

    $builder = \Drupal::entityTypeManager()->getViewBuilder($message->getEntityTypeId());
    $form['notification'] = $builder->view($message, 'teaser');
    $form['notification']['#weight'] = 1;

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit']['#button_type'] = 'primary';

    return $form;
  }

}
