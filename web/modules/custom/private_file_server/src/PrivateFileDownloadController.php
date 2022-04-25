<?php

namespace Drupal\private_file_server;

use Drupal\system\FileDownloadController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * This controller overrides the default file system's controller.
 */
class PrivateFileDownloadController extends FileDownloadController {

  /**
   * {@inheritdoc}
   */
  public function download(Request $request, $scheme = 'private') {
    $target = $request->query->get('file');
    $uri = sprintf('%s://%s', $scheme, $target);
    if ($this->streamWrapperManager->isValidScheme($scheme) && file_exists($uri)) {
      // Let other modules provide headers and controls access to the file.
      $headers = $this->moduleHandler()->invokeAll('file_download', [$uri]);

      foreach ($headers as $result) {
        if ($result == -1) {
          throw new AccessDeniedHttpException();
        }
      }

      // Currently we only add Etag to zip files.
      if (count($headers) &&
        isset($headers['Content-Type']) &&
        $headers['Content-Type'] === 'application/zip') {
        // \Drupal\Core\EventSubscriber\FinishResponseSubscriber::onRespond()
        // sets response as not cacheable if the Cache-Control header is not
        // already modified. We pass in FALSE for non-private schemes for the
        // $public parameter to make sure we don't change the headers.
        $response = new BinaryFileResponse($uri, '200', $headers, $scheme !== 'private', NULL, TRUE);
        // This isn't only gives back the a boolean value, but it manage the
        // status code and the body as well.
        $response->isNotModified($request);

        return $response;
      }
      elseif (!count($headers)) {
        throw new AccessDeniedHttpException();
      }

    }
    return parent::download($request, $scheme);
  }

}
