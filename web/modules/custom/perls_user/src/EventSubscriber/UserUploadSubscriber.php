<?php

namespace Drupal\perls_user\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles additional columns on the bulk user import.
 */
class UserUploadSubscriber implements EventSubscriberInterface {

  /**
   * Term array.
   *
   * @var array
   */
  public $possibleInterests;

  /**
   * Group array.
   *
   * @var array
   */
  public $possibleGroups;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'bulk_user_upload_user_import' => 'onUserImport',
    ];
  }

  /**
   * Constructs a \Drupal\views\EventSubscriber\RouteSubscriber instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $termStorage = $entity_type_manager->getStorage('taxonomy_term');
    $groupStorage = $entity_type_manager->getStorage('group');

    $this->possibleGroups = $groupStorage->loadByProperties(['type' => 'audience']);
    $this->possibleInterests = $termStorage->loadByProperties(['vid' => 'category']);
  }

  /**
   * Subscribe to the user import event dispatched.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   Event object.
   */
  public function onUserImport(Event $event) {
    if (isset($event->importUserRow['groups'])) {
      $groups_data = $event->importUserRow['groups'];
    }

    if (isset($event->importUserRow['interests'])) {
      $interests_data = $event->importUserRow['interests'];
    }

    // Import groups.
    if (!empty($groups_data)) {
      // Collect the selected group entities.
      $chosen_groups = explode('|', strtolower($groups_data));
      $group_search = array_filter($this->possibleGroups, function ($group) use ($chosen_groups) {
        /** @var \Drupal\group\Entity\GroupInterface $group */
        return in_array(strtolower($group->label()), $chosen_groups);
      });

      if ($group_search) {
        $field_add_groups = [];

        // Format the value to Drupal supported one.
        foreach ($group_search as $group) {
          $field_add_groups[] = ['target_id' => $group->id()];
        }

        $event->user->set('field_add_groups', $field_add_groups);
      }
    }

    // Import interests.
    if (!empty($interests_data)) {
      // Collect the selected interests terms.
      $chosen_interests = explode('|', strtolower($interests_data));
      $interests_search = array_filter($this->possibleInterests, function ($interest) use ($chosen_interests) {
        /** @var \Drupal\taxonomy\TermInterface $interest */
        return in_array(strtolower($interest->getName()), $chosen_interests);
      });

      if ($interests_search) {
        $field_interests = [];

        // Format the value to Drupal supported one.
        foreach ($interests_search as $interest) {
          $field_interests[] = ['target_id' => $interest->id()];
        }

        $event->user->set('field_interests', $field_interests);
      }
    }
  }

}
