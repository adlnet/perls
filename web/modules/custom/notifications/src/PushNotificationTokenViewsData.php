<?php

namespace Drupal\notifications;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the push notification token entity type.
 */
class PushNotificationTokenViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['users_field_data']['uid_pushnotify']['relationship'] = [
      'title' => $this->t('Device Registered'),
      'help' => $this->t('Relate registered devices to the user who created it. This relationship will create one record for each device item created by the user.'),
      'id' => 'standard',
      'base' => 'push_notifications_token',
      'base field' => 'auth_user_id',
      'field' => 'uid',
      'label' => $this->t('devices'),
    ];

    $data['users_field_data']['uid_listone'] = [
      'relationship' => [
        'title' => $this->t('List Single Registered Device'),
        'label'  => $this->t('List Single Registered Device'),
        'help' => $this->t('Obtains a single representative device for each user, according to a chosen sort criterion.'),
        'id' => 'groupwise_max',
        'relationship field' => 'uid',
        'outer field' => 'users_field_data.uid',
        'argument table' => 'users_field_data',
        'argument field' => 'uid',
        'base' => 'push_notifications_token',
        'field' => 'id',
        'relationship' => 'push_notifications_token:auth_user_id',
      ],
    ];

    return $data;
  }

}
