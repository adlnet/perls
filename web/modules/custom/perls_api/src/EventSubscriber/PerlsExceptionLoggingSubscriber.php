<?php

namespace Drupal\perls_api\EventSubscriber;

use Drupal\Core\EventSubscriber\ExceptionLoggingSubscriber;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Overrides exception.logger subscriber.
 */
class PerlsExceptionLoggingSubscriber extends ExceptionLoggingSubscriber {

  /**
   * {@inheritdoc}
   */
  public function onError(ExceptionEvent $event) {
    if ($event->getThrowable()->getPrevious() && $event->getThrowable()->getPrevious() instanceof OAuthServerException) {
      return;
    }
    parent::onError($event);
  }

}
