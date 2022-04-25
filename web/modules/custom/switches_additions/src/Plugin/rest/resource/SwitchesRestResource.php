<?php

namespace Drupal\switches_additions\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a resource to get configuration information.
 *
 * @RestResource(
 *   id = "switches_rest_resource",
 *   label = @Translation("Switches Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/switches"
 *   }
 * )
 */
class SwitchesRestResource extends ResourceBase {

  /**
   * Gets switch information.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The all switch entites.
   */
  public function get() {
    $manager = \Drupal::service('entity_type.manager')->getListBuilder('switch');
    $entities = $manager->load();
    $data = [];
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->addCacheTags(\Drupal::service('entity_type.manager')->getDefinition('switch')->getListCacheTags());
    $cache_metadata->addCacheContexts(\Drupal::service('entity_type.manager')->getDefinition('switch')->getListCacheContexts());
    if (isset($entities)) {
      $data["switches"] = $entities;
      foreach ($entities as $entity) {
        $cache_metadata->addCacheTags($entity->getCacheTags());
        $cache_metadata->addCacheContexts($entity->getCacheContexts());
      }
    }
    $response = new ResourceResponse($data);
    $response->addCacheableDependency($cache_metadata);
    return $response;
  }

}
