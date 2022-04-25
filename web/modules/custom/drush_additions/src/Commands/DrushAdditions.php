<?php

namespace Drupal\drush_additions\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;

/**
 * Drush commands.
 */
class DrushAdditions extends DrushCommands {

  /**
   * Drush commands for projects.
   */
  public function __construct() {
  }

  /**
   * Update field on user entity.
   *
   * @param string $mail
   *   - The user entity is looked up by this email address.
   * @param array $options
   *   Key value pair of options.
   *
   * @throws \Exception
   *
   * @command perls:update-user-field
   *
   * @aliases sluuf
   *
   * @options arr An option that takes multiple values.
   * @options field Name of field
   * @options value Value of field
   * @usage sluuf admin@example.com --field=field_name --value=Tom
   *   Sets field on user with email admin@example.com
   */
  public function updateUserField($mail, array $options = [
    'field' => InputOption::VALUE_REQUIRED,
    'value' => InputOption::VALUE_REQUIRED,
  ]) {
    $user = user_load_by_mail($mail);

    if (!$user) {
      throw new \Exception(dt('No user found with email: @mail.', ['@mail' => $mail]));
    }

    if ($user->hasField($options['field'])) {
      $user->set($options['field'], $options['value']);
      $user->save();
      $this->output()->writeln($options['field'] . ' updated to ' . $options['value'] . ' for user ' . $user->id());
      return;
    }

    throw new \Exception(dt('User was found. But the field was not.'));
  }

  /**
   * Creates or updates a Simple Oauth consumer.
   *
   * @param string $uuid
   *   The desired client ID (must be a UUID).
   * @param string $secret
   *   The desired client secret.
   * @param array $options
   *   Additional options for the consumer.
   *
   * @usage perls:createConsumer uuid secret --label="My Custom Client"
   *   Creates a new OAuth consumer.
   *
   * @command perls:createConsumer
   * @option owner_id
   *   The owner of the consumer.
   * @option label
   *   A label for the consumer.
   * @option description
   *   A description for the consumer.
   * @option third_party
   *   Whether you or a third party will use this consumer.
   * @option confidential
   *   Whether the client secret needs to be validated or not.
   * @option is_default
   *   Whether this is the default consumer for the site.
   * @option redirect
   *   The URI this client will redirect to when needed.
   * @option user_id
   *   Default user.
   * @option roles
   *   The roles for this Consumer.
   */
  public function createOrUpdateConsumer($uuid, $secret, array $options = [
    'owner_id' => 1,
    'label' => 'New Consumer',
    'description' => InputOption::VALUE_OPTIONAL,
    'third_party' => TRUE,
    'confidential' => TRUE,
    'is_default' => FALSE,
    'redirect' => InputOption::VALUE_OPTIONAL,
    'user_id' => InputOption::VALUE_OPTIONAL,
    'roles' => [],
  ]) {
    $consumer_storage = \Drupal::entityTypeManager()->getStorage('consumer');

    // Delete the existing consumer if it already exists.
    try {
      $existing_consumer = \Drupal::service('entity.repository')->loadEntityByUuid('consumer', $uuid);
      if ($existing_consumer) {
        $existing_consumer->delete();
      }
    }
    catch (\Exception $e) {
    }

    $values = [
      'uuid' => $uuid,
      'secret' => $secret,
    ] + $options;

    $new_consumer = $consumer_storage->create($values);
    $new_consumer->save();

    $this->logger()->notice('Created oAuth consumer {label}', ['label' => $new_consumer->label()]);
  }

}
