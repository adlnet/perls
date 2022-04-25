<?php

namespace Drupal\react_comments\Plugin\rest\resource;

use Drupal\react_comments\Model\User;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\react_comments\Model\Response as ResponseModel;
use Drupal\react_comments\Model\Me as MeModel;
use Drupal\rest\ResourceResponse;

/**
 * Provides comments for a given node resource.
 *
 * @RestResource(
 *   id = "me",
 *   label = @Translation("Me"),
 *   uri_paths = {
 *   "canonical" = "/react-comments/me",
 *   "https://www.drupal.org/link-relations/create" = "/react-comments/me"
 *   }
 * )
 */
class Me extends ResourceBase {

  public function get() {
    // @todo dependency injection
    $current_user = \Drupal::currentUser();
    $response = new ResponseModel();

    $user = new User($current_user);
    $me = new MeModel();
    $me->setCurrentUser($user);
    $response->setData($me->model())
      ->setCode('success');

    $response = (new ResourceResponse($response->model()));
    $response->getCacheableMetadata()->setCacheContexts(['user']);
    $response->addCacheableDependency($current_user);
    return $response;
  }

}
