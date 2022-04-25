<?php

namespace Drupal\bulk_user_upload;

use Drupal\Core\Entity\EntityConstraintViolationList;
use Drupal\bulk_user_upload\Event\UserImportEvent;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

/**
 * Imports and validates users.
 */
class BulkUserUpload {

  /**
   * Determines the available roles for importing users.
   *
   * @return array
   *   An array of possible roles to assign to imported users.
   */
  public static function availableRoles() {
    $user = \Drupal::currentUser();
    $roleAccessManager = \Drupal::service('administerusersbyrole.access');

    if (!$roleAccessManager || $user->hasPermission('administer users')) {
      $roles = user_roles(TRUE);
    }
    else {
      $rids = $roleAccessManager->listRoles('role-assign', $user);
      $roles = Role::loadMultiple($rids);
    }

    return array_filter($roles, function ($role) {
      return !$role->isAdmin();
    });
  }

  /**
   * Main batch process function.
   *
   * Validates and creates each import record in each batch.
   */
  public static function uploadUserProcess($users, $default_role, &$context) {
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $current_user = \Drupal::currentUser();
    $messenger = \Drupal::messenger();

    // Determine the roles that the user uploading the CSV is allowed to assign.
    $possible_roles = self::availableRoles();
    if (!isset($possible_roles[$default_role]) || $default_role === RoleInterface::AUTHENTICATED_ID) {
      $default_role = NULL;
    }

    $results = isset($context['results']) ? $context['results'] : [];

    foreach ($users as $import_user) {
      $row_number = $import_user['row_number'];
      $email = $import_user['email'];

      if (isset($import_user['name'])) {
        $name = $import_user['name'];
      }

      if (isset($import_user['password'])) {
        $password = $import_user['password'];
      }

      if (isset($import_user['role'])) {
        $role_data = $import_user['role'];
      }

      if (empty($email)) {
        $messenger->addWarning(t('An email address is missing for the user at row @row.', ['@row' => $row_number]));
      }
      else {
        $dup_check = user_load_by_mail($email);
        if (!$dup_check) {
          $dup_check = user_load_by_name($email);
        }
        if ($dup_check) {
          $messenger->addWarning(t('The user at row @row already exists and was skipped.', ['@row' => $row_number]));
        }
        else {
          // No duplicates or missing information detected, create user record.
          /** @var \Drupal\user\UserInterface $user */
          $user = User::create();

          $user->setUsername($email);
          $user->setEmail($email);

          if (!empty($name)) {
            $user->field_name->value = $name;
          }

          if (!empty($password)) {
            $user->setPassword($password);
          }

          // Handle adding specific role, default to role from upload form.
          if (!empty($role_data)) {
            $role_search = array_filter($possible_roles, function ($role) use ($role_data) {
              return strtolower($role->get('label')) === strtolower($role_data);
            });

            if ($role_search) {
              $role_to_add = reset($role_search);
              if ($role_to_add->id() !== RoleInterface::AUTHENTICATED_ID) {
                $user->addRole($role_to_add->getOriginalId());
              }
            }
            else {
              $messenger->addWarning(t('Unable to assign a role of %role to %user at row @row.', [
                '%role' => $role_data,
                '%user' => $email,
                '@row' => $row_number,
              ]));
            }
          }
          elseif ($default_role) {
            $user->addRole($default_role);
          }

          // Dispatch event so other modules can import their custom fields.
          $event = new UserImportEvent($user, $import_user);
          $event_dispatcher->dispatch(UserImportEvent::EVENT_NAME, $event);

          $user->enforceIsNew();

          $user->set('init', $import_user[1]);
          $user->set('langcode', $lang);
          $user->set('preferred_langcode', $lang);
          $user->set('preferred_admin_langcode', $lang);
          $user->activate();

          // Validate data.
          $violations = $user->validate();
          if ($violations->count() > 0) {
            $messenger->addWarning(t('Unable to import user at row @row. @message', [
              '@row' => $row_number,
              '@message' => self::formatViolations($violations),
            ]));
            continue;
          }

          // Save user.
          $result = $user->save();
          $results[] = $result->uid;
        }
      }
    }
    $context['results'] = $results;
  }

  /**
   * Batch processing callback.
   */
  public static function uploadUserFinishedCallback($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'One user account created.', '@count user accounts created.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }
    $messenger = \Drupal::messenger();
    $messenger->addStatus($message);
  }

  /**
   * Prepares a user-friendly(ish) description of the invalid data.
   *
   * @param \Drupal\Core\Entity\EntityConstraintViolationList $violations
   *   The violations encountered when validating the data.
   *
   * @return string
   *   A message to display to the user.
   */
  private static function formatViolations(EntityConstraintViolationList $violations) {
    $messages = [];
    $entity = $violations->getEntity();
    foreach ($violations as $violation) {
      if (!($path = $violation->getPropertyPath())) {
        continue;
      }

      list($field_name) = explode('.', $path, 2);
      $label = $entity->getFieldDefinition($field_name)->getLabel();
      $messages[] = t('@label: @message', [
        '@label' => $label,
        '@message' => $violation->getMessage(),
      ]);
    }

    return implode(', ', $messages);
  }

}
