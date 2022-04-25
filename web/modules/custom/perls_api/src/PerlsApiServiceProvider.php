<?php

namespace Drupal\perls_api;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Module's service provider.
 */
class PerlsApiServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('exception.logger')) {
      $definition = $container->getDefinition('exception.logger');
      $definition->setClass('Drupal\perls_api\EventSubscriber\PerlsExceptionLoggingSubscriber');
    }
  }

}
