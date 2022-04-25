<?php

namespace Drupal\notifications;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\notifications\Entity\PushNotification;
use Drupal\Core\Datetime\DrupalDatetime;

/**
 * Defines a class to build a listing of Access entities.
 *
 * @ingroup notifications
 */
class PushNotificationListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['user'] = $this->t('Sender');
    $header['recipients'] = $this->t('Recipients');
    $header['title'] = $this->t('Title');
    $header['status'] = $this->t('Message Status');
    $header['send_time'] = $this->t('Send/Sent at:');
    $header['content'] = $this->t('Content');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\notifications\Entity\PushNotification $entity */
    $row['id'] = $entity->id();
    $row['user'] = NULL;
    $row['recipients'] = NULL;
    $row['title'] = $entity->toLink(sprintf('%s…', substr($entity->label(), 0, 10)));
    $row['status'] = NULL;
    $row['send_time'] = NULL;
    $row['content'] = NULL;
    if (($user = $entity->get('auth_user_id')) && $user->entity) {
      $row['user'] = $user->entity->toLink($user->entity->label());
    }
    if (($recipients = $entity->get('recipients')->referencedEntities())) {
      foreach ($recipients as $user) {
        $row['recipients'] .= $user->label() . ', ';
      }
    }
    if (($topics = $entity->get('topics')->referencedEntities())) {
      if ($row['recipients']) {
        $row['recipients'] .= '\n';
      }
      $row['recipients'] .= 'Topics: ';
      foreach ($topics as $topic) {
        $row['recipients'] .= $topic->label() . ', ';
      }
    }
    if (($device = $entity->get('content'))) {
      $row['content'] = sprintf('%s…', substr($device->getString(), 0, 10));
    }
    $status = $entity->getSendStatus();
    if ($status == PushNotification::PUSH_NOTIFICATION_PENDING) {
      $row['status'] = $this->t('Queued');
    }
    if ($status == PushNotification::PUSH_NOTIFICATION_SENT) {
      $row['status'] = $this->t('Sent');
    }
    if ($status == PushNotification::PUSH_NOTIFICATION_CANCELLED) {
      $row['status'] = $this->t('Cancelled');
    }
    if ($sendtime = $entity->get('send_time')->value) {
      $timezone = date_default_timezone_get();
      $sendtime = DrupalDatetime::createFromTimestamp($sendtime, $timezone);
      $row['send_time'] = $sendtime->format('Y/m/d H:i');
    }
    return $row + parent::buildRow($entity);
  }

  /**
   * This method returns the operations list for the entity for use in views.
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    if ($entity->access('cancel') && $entity->hasLinkTemplate('cancel-form')) {
      $operations['cancel'] = [
        'title' => $this->t('Cancel'),
        'weight' => 20,
        'url' => $this->ensureDestination($entity->toUrl('cancel-form')),
      ];
    }
    if ($entity->access('sendnow') && $entity->hasLinkTemplate('send-form')) {
      $operations['sendnow'] = [
        'title' => ($entity->getSendStatus() == 1) ? $this->t('Resend') : $this->t('Send Now'),
        'weight' => 20,
        'url' => $this->ensureDestination($entity->toUrl('send-form')),
      ];
    }
    return $operations;
  }

}
