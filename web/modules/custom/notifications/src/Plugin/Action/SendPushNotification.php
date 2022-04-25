<?php

namespace Drupal\notifications\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;
use Drupal\notifications\Form\ViewsBulkOperationsFormTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Send Firebase cloud message to selected users.
 *
 * @Action (
 *   id = "send_push_notification",
 *   action_label = @Translation("Send push notification"),
 *   confirm = TRUE,
 *   confirm_form_route_name =
 *     "notifications.SendPushNotification.confirm",
 *   requirements = {
 *     "_permission" = "send push notifications",
 *   },
 *   deriver = "Drupal\notifications\Plugin\Action\Derivative\SendPushNotificationDeriver",
 * )
 */
class SendPushNotification extends ViewsBulkOperationsActionBase implements PluginFormInterface {

  use StringTranslationTrait;
  use ViewsBulkOperationsFormTrait;

  /**
   * The push notification entity.
   *
   * @var \Drupal\notifications\Entity\PushNotification
   */
  protected $messageEntity;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // We need to get the user id to search for device tokens.
    $this->messageEntityId = $this->configuration['messageEntityId'];
    $messageEntity = \Drupal::entityTypeManager()->getStorage('push_notification')->load($this->configuration['messageEntityId']);

    $recipients = $this->getRecipients($entity);
    foreach ($recipients as $recipient) {
      $messageEntity->addUser($recipient->id());
    }
    if ($entity instanceof Term) {
      $messageEntity->addTopic($entity->id());
    }
    $messageEntity->save();

    if (empty($recipients) && empty($messageEntity->topics->getValue())) {
      return $this->t('Unable to send message');
    }

    if ($this->configuration['send_timing'] == 'send_now') {
      /** @var \Drupal\notifications\Service\ExtendedFirebaseMessageService $messageService */
      $messageService = \Drupal::service('notifications.firebase.message');
      $response = $messageService->sendMessage($messageEntity, $entity);

      if (!$response) {
        return $this->t('Notification failed to send; no response from Firebase');
      }

      $success = $response['success'] > 0 || $response['message_id'];
      if (!$success) {
        return $this->t('Unable to send message to @type; see log for details', [
          '@type' => $entity->getEntityType()->getSingularLabel(),
        ]);
      }

      return $this->t('Message sent to @type', [
        '@type' => $entity->getEntityType()->getSingularLabel(),
      ]);
    }
    else {
      return $this->t('Notification scheduled for @type', [
        '@type' => $entity->getEntityType()->getSingularLabel(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['#title'] = $this->t('Compose Push Notification');

    unset($form['list']);
    $form['instructions'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $this->t('Creating a message to send to @recipients', [
        '@recipients' => $this->getSelectionSummary($this->context),
      ]),
    ];

    $form['message_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#description' => $this->t("Provide a specific and succinct title; the title is the most prominent part of the message when shown on the user's device. You can use Emoji here.<br><small>The title can be up to 128 characters.</small>"),
      '#placeholder' => $this->t('Type a title for the message'),
    ];
    $form['message_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
      '#description' => $this->t('Try to keep the message brief. Some devices may require the user to tap into the notification to see the full message. Not all devices let users copy text from a message, so avoid sending URLs, email addresses, or important codes.<br><small>The message can be up to 1000 characters.</small>'),
      '#placeholder' => $this->t('Type a message'),
      '#rows' => 4,
      '#resizable' => 'none',
      '#attributes' => [
        'maxlength' => 1000,
      ],
    ];

    $form['message_priority'] = [
      '#title' => $this->t('Priority'),
      '#type' => 'select',
      '#description' => $this->t('The priority does not affect how the message appears to the user. A message sent with normal priority may be delayed in reaching the user depending on their device settings and network conditions.'),
      '#options' => [
        'normal' => $this->t('Normal'),
        'high' => $this->t('High'),
      ],
      '#weight' => 10,
    ];

    $form['send_timing'] = [
      '#type' => 'radios',
      '#title' => t('Timing'),
      '#options' => [
        'send_now' => t('Send now'),
        'schedule' => t('Schedule for later'),
      ],
      '#default_value' => 'send_now',
      '#weight' => 20,
    ];

    $form['timing_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => 'send_time',
      ],
      '#states' => [
        'visible' => [
          ':input[name="send_timing"]' => [
            'value' => 'schedule',
          ],
        ],
      ],
      '#weight' => 20,
    ];

    $default_date = new DrupalDateTime();

    $default_date = $default_date
      ->setTime($default_date->format('H'), 0)
      ->modify('next day');

    $form['timing_container']['send_time'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Schedule'),
      '#size' => 20,
      '#date_date_element' => 'date',
      '#date_time_element' => 'time',
      '#date_increment' => 60 * 15,
      '#default_value' => $default_date,
      '#date_time_callbacks' => ['_notifications_datetime_time'],
    ];

    $form['actions']['submit']['#value'] = $this->t('Send');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (strlen($form_state->getValue('message_body')) > 1000) {
      $form_state->setErrorByName('message_body', $this->t('The message must be 1000 characters or less.'));
    }
    // Check that schedule message is in future.
    if ($form_state->getValue('send_timing') === 'schedule') {
      $time = \Drupal::time()->getCurrentTime();
      $saved_time = $form_state->getValue('send_time');
      $saved_time = $saved_time->getTimestamp();
      if ($time >= $saved_time) {
        $form_state->setErrorByName('send_time', $this->t('When scheduling a message, it must be scheduled for the future.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $config['notification']['title'] = $form_state->getValue('message_title');
    $config['notification']['body'] = $form_state->getValue('message_body');
    $config['options']['priority'] = $form_state->getValue('message_priority');
    $this->configuration['send_timing'] = $form_state->getValue('send_timing');

    /** @var \Drupal\notifications\Service\ExtendedFirebaseMessageService $messageService */
    $messageService = \Drupal::service('notifications.firebase.message');
    $this->messageEntity = $messageService->createMessage($config);

    if ($form_state->getValue('send_timing') === 'send_now') {
      $this->messageEntity->sent();
    }
    else {
      $this->messageEntity->setSendTime($form_state->getValue('send_time')->getTimestamp());
    }
    $this->messageEntity->save();

    $this->configuration['messageEntityId'] = $this->messageEntity->id();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return AccessResult::allowedIf($account->hasPermission('send push notifications'));
  }

  /**
   * Retrieves a list of recipients to add to the push notification.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity that represents a single or group of users.
   *
   * @return array
   *   The user objects to add to the notification.
   */
  protected function getRecipients(EntityInterface $entity): array {
    if ($entity instanceof UserInterface) {
      return [$entity];
    }

    if ($entity->getEntityTypeId() === 'group') {
      return array_map(function ($membership) {
        return $membership->getUser();
      }, $entity->getMembers());
    }

    return [];
  }

}
