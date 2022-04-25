<?php

namespace Drupal\private_file_server\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Set proper Content-type of a response.
 *
 * We need to handle a core bug which doesn't set properly the response of
 * privates files. The symfony 3 to 4 change introduced a new logic in
 * Symfony\Component\HttpFoundation\File::getMimeType() because it use MimeTypes
 * class not the MimeTypeGuesser where the drupal register own mime type
 * guesser.
 */
class FileResponseEventSubscriber implements EventSubscriberInterface {

  /**
   * Drupal mime type guesser service.
   *
   * @var \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface
   */
  protected $mimeTypeGuesser;

  /**
   * FileResponseEventSubscriber constructor.
   *
   * @param \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface $mime_type_guesser
   *   Drupal mime type guesser service.
   */
  public function __construct(MimeTypeGuesserInterface $mime_type_guesser) {
    $this->mimeTypeGuesser = $mime_type_guesser;
  }

  /**
   * {@inheritdoc}
   */
  public function onRespond(ResponseEvent $event) {
    $response = $event->getResponse();
    if (!$response instanceof BinaryFileResponse) {
      return;
    }
    $path = $response->getFile()->getPathname();
    if ($path) {
      $mimeType = $this->mimeTypeGuesser->guess($path);
      $response->headers->set('Content-Type', $mimeType);
      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onRespond'];
    return $events;
  }

}
