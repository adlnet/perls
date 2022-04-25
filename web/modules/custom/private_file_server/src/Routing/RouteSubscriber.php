<?php

namespace Drupal\private_file_server\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alter file system controller.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $collection->get('system.files')
      ->setDefault('_controller', 'Drupal\private_file_server\PrivateFileDownloadController::download');
    $collection->get('system.private_file_download')
      ->setDefault('_controller', 'Drupal\private_file_server\PrivateFileDownloadController::download');
    $collection->get('system.temporary')
      ->setDefault('_controller', 'Drupal\private_file_server\PrivateFileDownloadController::download');
  }

}
