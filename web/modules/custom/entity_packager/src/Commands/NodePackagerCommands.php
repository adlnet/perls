<?php

namespace Drupal\entity_packager\Commands;

use Drupal\entity_packager\NodePackageBatchGenerator;
use Drush\Drupal\Commands\core\MessengerCommands;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Drush commands for node packager module.
 */
class NodePackagerCommands extends MessengerCommands {

  /**
   * Helper service to manage batching page generate.
   *
   * @var \Drupal\entity_packager\EntityPackager
   */
  protected $generator;

  /**
   * OfflinePageGeneratorCommand constructor.
   *
   * @param \Drupal\entity_packager\NodePackageBatchGenerator $generator
   *   A helper service to batch generate offline files.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(NodePackageBatchGenerator $generator, MessengerInterface $messenger) {
    parent::__construct($messenger);
    $this->generator = $generator;
  }

  /**
   * Drush command which regenerate offline package on nodes.
   *
   * @command entity_packager:generate
   * @bootstrap full
   */
  public function generator() {
    $this->generator->generate();
    drush_backend_batch_process();
  }

}
