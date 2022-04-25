<?php

namespace Drupal\perls_xapi_reporting;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * This service provider overrides the statement creator service.
 */
class PerlsXapiReportingServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('xapi.activity_provider')) {
      $definition = $container->getDefinition('xapi.activity_provider');
      $definition->setClass(PerlsXapiActivityProvider::class);
    }
  }

}
