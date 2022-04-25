<?php

namespace Drupal\notifications\Service;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\firebase\Service\FirebaseMessageService;
use Drupal\notifications\Event\PushNotificationToUser;
use Drupal\notifications\Entity\PushNotification;

/**
 * Service for pushing message to mobile devices using Firebase.
 */
class ExtendedFirebaseMessageService extends FirebaseMessageService {

  /**
   * This method is used to return the message body to be saved.
   */
  private function getMessageBody() {
    $temp = $this->body;
    unset($temp['registration_ids']);
    unset($temp['to']);
    unset($temp['condition']);
    return Json::encode($temp);
  }

  /**
   * This method is used to return the message body to be saved.
   */
  private function setMessageBody($message) {
    $this->body = Json::decode($message);
  }

  /**
   * Send a push notification to a user.
   */
  public function sendPushNotification($title, $message, $recipient_id, $data = NULL) {
    $message = [
      'notification' => [
        'title' => PlainTextOutput::renderFromHtml($title),
        'body' => PlainTextOutput::renderFromHtml($message),
        'click_action' => 'HandleNotification',
      ],
      'options' => [
        'priority' => 'normal',
      ],
    ];

    if ($data !== NULL) {
      $message['data'] = $data;
    }

    $recipient = \Drupal::entityTypeManager()->getStorage('user')->load($recipient_id);
    $push_notification = $this->createMessage($message);
    $push_notification->setSendTime(time());
    $push_notification->addUser($recipient_id);
    $push_notification->save();
    try {
      $this->sendMessage($push_notification, $recipient);
    }
    catch (\Exception $e) {
    }
  }

  /**
   * This method sends notification to user entities.
   */
  protected function sendToUsers(PushNotification $message, $recipients) {
    $list_recipients = '';
    $user_ids = [];
    foreach ($recipients as $user) {
      $list_recipients .= $user->label() . ', ';
      $user_ids[] = $user->id();
    }
    // Users set so send to users.
    $tokens = $this->getDeviceTokens($user_ids);
    // If no valid tokens exist stop here.
    if (empty($tokens)) {
      // Cancel message and log problem.
      $message->cancel();
      $message->set('send_time', \Drupal::time()->getCurrentTime());
      $message->save();
      \Drupal::logger('notifications')
        ->notice('Failed to find any valid devices for push notification message');
    }
    else {
      // Each message can only have 1000 recipients.
      $tokens = array_chunk($tokens, 990, TRUE);
      foreach ($tokens as $token_chunk) {
        $this->resetService();
        $this->setMessageBody($message->get('content')->value);
        $this->setRecipients(array_values($token_chunk));
        $response = $this->send();
        $message->sent();
        $message->set('send_time', \Drupal::time()->getCurrentTime());
        $message->save();
        if (!empty($response)) {
          $this->cleanUpTokens($response, $token_chunk);
        }
      }
      \Drupal::logger('notifications')
        ->notice('Push Notification title \'@title\' has been sent to the following recipients:  @users', [
          '@title' => $message->label(),
          '@users' => $list_recipients,
        ]);
      return $response;
    }
    return [];

  }

  /**
   * This method is used to send notifications to Topics.
   */
  protected function sendToTopics(PushNotification $message, $topics) {
    $list_topics = '';
    $topic_ids = [];
    foreach ($topics as $topic) {
      $list_topics .= $topic->label() . ', ';
      $topic_machine_name = urlencode($topic->label());
      $topic_ids[$topic_machine_name] = $topic->label();

    }

    // If no valid tokens exist stop here.
    if (empty($topic_ids)) {
      \Drupal::logger('notifications')
        ->notice('Failed to find any topic for push notification message');
    }
    else {
      foreach ($topic_ids as $topic_machine_name => $topic_id) {
        $this->resetService();
        $this->setMessageBody($message->get('content')->value);
        $this->setTopics($topic_machine_name);
        $response = $this->send();
        $message->sent();
        $message->set('send_time', \Drupal::time()->getCurrentTime());
        $message->save();
      }
      if ($response['message_id']) {
        \Drupal::logger('notifications')
          ->notice('Push Notification title \'@title\' has been sent to the following topics:  @topic', [
            '@title' => $message->label(),
            '@topic' => $list_topics,
          ]);
      }
      else {
        \Drupal::logger('notifications')
          ->notice('Push Notification title \'@title\' has failed to send to Topics:  @topic with error message @error',
          [
            '@title' => $message->label(),
            '@topic' => $list_topics,
            '@error' => $response['error'],
          ]);
      }
      return $response;
    }
    return [];

  }

  /**
   * This method deletes tokesn no longer valid.
   */
  private function cleanUpTokens($response, $tokens) {
    $ids = array_keys($tokens);
    if ($response['failure'] > 0) {
      foreach ($response['results'] as $index => $value) {
        if (isset($value['error']) && $errorMsg = $value['error']) {
          if ($errorMsg == 'InvalidRegistration' || $errorMsg == 'NotRegistered') {
            $storageManager = \Drupal::entityTypeManager()->getStorage('push_notification_token');
            $token_to_delete = $storageManager->load($ids[$index]);
            if ($token_to_delete) {
              \Drupal::logger('notifications')
                ->notice('Deleting Token @id with value @value.', [
                  '@id' => $token_to_delete->id(),
                  '@value' => $token_to_delete->label(),
                ]);
              $storageManager->delete([$token_to_delete]);
            }
          }
        }
      }
    }
  }

  /**
   * Check if a user is registered for push notifications.
   */
  public function isRegisteredForNotifications($user_id) {
    return !empty($this->getDeviceTokens($user_id));
  }

  /**
   * This method returns all device ids for the given users.
   */
  private function getDeviceTokens($user_ids) {
    if (!is_array($user_ids)) {
      $user_ids = [$user_ids];
    }
    $tokens = [];
    foreach ($user_ids as $user_id) {
      $query = \Drupal::entityQuery('push_notification_token')
        ->condition('status', 1)
        ->condition('auth_user_id', $user_id);
      $ids = $query->execute();

      // We can bulk add all these devices in a single ping to firebase but we
      // need array of tokens not entity ids.
      foreach ($ids as $id) {
        /** @var \Drupal\notifications\Entity\PushNotificationToken $push_token_entity */
        $push_token_entity = \Drupal::entityTypeManager()->getStorage('push_notification_token')->load($id);
        // Filter out the duplicated tokens.
        if (!in_array($push_token_entity->get('value')->value, $tokens)) {
          $tokens[$id] = $push_token_entity->get('value')->value;
        }
      }
    }
    return $tokens;
  }

  /**
   * This method is used to send message from entity.
   */
  public function sendMessage(PushNotification $message, EntityInterface $entity = NULL) {
    if ($entity) {
      // Entity supplied only send to this.
      $recipients = [];
      $topics = [];
      if ($entity->getEntityTypeId() == 'user') {
        $recipients[] = $entity;
      }
      elseif ($entity->getEntityTypeId() == 'taxonomy_term') {
        $topics[] = $entity;
      }
      elseif ($entity->getEntityTypeId() == 'group') {
        $members = $entity->getMembers();
        foreach ($members as $member) {
          $recipients[] = $member->getUser();
        }
      }
      else {
        \Drupal::logger('notifications')
          ->warning('Push Notification title \'@title\' has failed to send: Entity type: @entity not supported.',
          [
            '@title' => $message->label(),
            '@entity' => $entity->getEntityTypeId(),
          ]);
        return NULL;
      }
    }
    else {
      // Use message data.
      $recipients = $message->get('recipients')->referencedEntities();
      $topics = $message->get('topics')->referencedEntities();
    }

    // Dispatching the event and calling the subscriber.
    \Drupal::service('event_dispatcher')->dispatch(PushNotificationToUser::PUSH_NOTIFICATION_TO_USER, new PushNotificationToUser($message));

    if (!empty($recipients)) {
      $response = $this->sendToUsers($message, $recipients);
    }
    if (!empty($topics)) {
      $response = $this->sendToTopics($message, $topics);
    }

    return $response;
  }

  /**
   * This method creates a push notification entity from data provided.
   */
  public function createMessage(array $message_data) {
    $author_id = ($author = \Drupal::currentUser()) ? $author->id() : 0;
    $this->resetService();
    $notification = $this->getNotification($message_data);
    if (!empty($notification)) {
      $this->setNotification($notification);
    }
    $data = $this->getData($message_data);
    if (!empty($data)) {
      $this->setData($data);
    }
    $this->setOptions($this->getOptions($message_data));

    $returnEntity = PushNotification::create([
      'title' => $message_data['notification']['title'],
      'content' => $this->getMessageBody(),
      'auth_user_id' => $author_id,
      'status' => TRUE,
    ]);
    $returnEntity->save();
    return $returnEntity;
  }

  /**
   * Function to return the Notification array.
   */
  private function getNotification(array $config) {
    $notification_elements = [
      'title',
      'body',
      'badge',
      'icon',
      'sound',
      'click_action',
      'image',
    ];
    return $this->buildConfigurationArray($notification_elements, $config['notification']);
  }

  /**
   * Function to return the Options array.
   */
  private function getOptions(array $config) {
    $options_elements = [
      'priority',
      'content_available',
      'mutable_content',
      'time_to_live',
      'dry_run',
    ];
    return $this->buildConfigurationArray($options_elements, $config['options']);
  }

  /**
   * Function to return the Data array.
   */
  private function getData(array $config) {
    if (isset($config['data'])) {
      return $config['data'];
    }
    return [];
  }

  /**
   * Check config for value associate with supplied keys.
   *
   * @param array $elements
   *   A list of elements to check for in config.
   * @param array $config
   *   An array containing message configuaration data.
   */
  private function buildConfigurationArray(array $elements, array $config) {
    $config_array = [];
    foreach ($elements as $element_id) {
      if (isset($config[$element_id])) {
        $config_array[$element_id] = $config[$element_id];
      }
    }
    return $config_array;
  }

}
