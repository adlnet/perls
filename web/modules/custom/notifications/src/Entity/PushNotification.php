<?php

namespace Drupal\notifications\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the Push Notification entity.
 *
 * @ingroup notifications
 *
 * @ContentEntityType(
 *   id = "push_notification",
 *   label = @Translation("Push notification"),
 *   bundle_label = @Translation("content type"),
 *   handlers = {
 *     "list_builder" = "Drupal\notifications\PushNotificationListBuilder",
 *     "form" = {
 *       "edit" = "Drupal\notifications\Entity\Form\PushNotificationForm",
 *       "cancel" = "Drupal\notifications\Entity\Form\PushNotificationCancelForm",
 *       "sendnow" = "Drupal\notifications\Entity\Form\PushNotificationSendNowForm",
 *       "delete" = "Drupal\notifications\Entity\Form\PushNotificationDeleteForm",
 *     },
 *     "access" = "Drupal\notifications\PushNotificationAccessControlHandler",
 *     "views_data" = "Drupal\notifications\PushNotificationViewsData"
 *   },
 *   base_table = "push_notification",
 *   admin_permission = "administer push notifications",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/manage-push-notifications/notification/{push_notification}",
 *     "delete-form" = "/manage-push-notifications/notification/{push_notification}/delete",
 *     "edit-form" = "/manage-push-notifications/notification/{push_notification}/edit",
 *     "cancel-form" = "/manage-push-notifications/notification/{push_notification}/cancel",
 *     "send-form" = "/manage-push-notifications/notification/{push_notification}/sendnow"
 *   }
 * )
 */
class PushNotification extends ContentEntityBase {

  use EntityChangedTrait;

  // Status constants.
  const PUSH_NOTIFICATION_PENDING = 0;
  const PUSH_NOTIFICATION_SENT = 1;
  const PUSH_NOTIFICATION_CANCELLED = 2;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Push Notification entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Push Notification entity.'))
      ->setReadOnly(TRUE);

    $fields['auth_user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sender'))
      ->setDescription(t('The user ID of the user who sent the message.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\node\Entity\Node::getDefaultEntityOwner')
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 1,
      ])
      ->setCardinality(1);

    $fields['recipients'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Recipients'))
      ->setDescription(t('Add users to this message.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(FALSE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete_tags',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'user',
          'placeholder' => '',
        ],
      ]);
    $fields['topics'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Topics'))
      ->setDescription(t('Topics allow you to send to all users who subscribe to a given topic.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'tags',
        'weight' => 1,
      ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete_tags',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ]);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('This is the title of the push notification .'))
      ->setSettings([
        'max_length' => 1024,
        'text_processing' => 0,
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'label',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'label' => 'above',
        'type' => 'textfield',
        'weight' => 4,
      ]);
    $fields['content'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Notification Content'))
      ->setDescription(t('The entire message content in Json format'))
      ->setSettings([
        'max_length' => 4048,
        'text_processing' => 0,
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'label' => 'above',
        'type' => 'textfield',
        'weight' => 4,
      ]);
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the message is available.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(TRUE);

    $fields['sent'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Message Sent'))
      ->setDescription(t('A int indicating whether the message is pending, sent or cancelled.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(PushNotification::PUSH_NOTIFICATION_PENDING);

    $fields['send_time'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Send at'))
      ->setDescription(t('The time that the message should be sent.'))
      ->setSettings([
        'datetime_type' => 'datetime',
      ])
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'label' => 'inline',
        'type' => 'datetime_timestamp',
        'weight' => 6,
      ]);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function revoke() {
    $this->set('status', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function isRevoked() {
    return !$this->get('status')->value;
  }

  /**
   * Append a user to recipients list.
   */
  public function addUser($user_id) {
    return $this->get('recipients')->appendItem($user_id);
  }

  /**
   * Return list of users associated with this notification.
   */
  public function getUsers() {
    return $this->get('recipients');
  }

  /**
   * Append a topic to the topics list.
   */
  public function addTopic($topic) {
    return $this->get('topics')->appendItem($topic);
  }

  /**
   * Return a list of topics associated with notification.
   */
  public function getTopics() {
    return $this->get('topics');
  }

  /**
   * Mark this notification as sent.
   */
  public function sent() {
    $this->set('sent', PushNotification::PUSH_NOTIFICATION_SENT);
  }

  /**
   * Mark this notification as cancelled.
   */
  public function cancel() {
    $this->set('sent', PushNotification::PUSH_NOTIFICATION_CANCELLED);
  }

  /**
   * Return the status of this notification 0 = queued, 1 = sent, 2 = cancelled.
   */
  public function getSendStatus() {
    return (int) $this->get('sent')->value;
  }

  /**
   * Set the send/sent type for this message.
   */
  public function setSendTime($datetime) {
    $this->set('send_time', $datetime);
  }

  /**
   * Retrieves the notification body.
   */
  public function getBody(): string {
    $notification = $this->getMessageNotification();
    return isset($notification['body']) ? $notification['body'] : NULL;
  }

  /**
   * Retrieves the notification object in the message.
   */
  public function getMessageNotification(): array {
    return $this->getMessage()['notification'];
  }

  /**
   * Retrieves the data object in the message.
   */
  public function getMessageData(): ?array {
    $message = $this->getMessage();
    return isset($message['data']) ? $message['data'] : NULL;
  }

  /**
   * Set new message content.
   */
  public function setMessage(array $message) {
    $this->set('content', Json::encode($message));
  }

  /**
   * Retrieves the message content.
   *
   * @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#Notification
   */
  public function getMessage(): array {
    return Json::decode($this->content->getString());
  }

}
