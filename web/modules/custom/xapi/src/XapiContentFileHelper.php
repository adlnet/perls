<?php

namespace Drupal\xapi;

use Drupal\Core\Url;
use Drupal\xapi\Plugin\Field\FieldType\XapiContentFileItem;
use Drupal\user\Entity\User;

/**
 * Help to generate url for learning package zip.
 */
class XapiContentFileHelper {

  /**
   * Retrieves a fully-qualified launch URL for the xAPI activity.
   *
   * Includes LRS endpoint and actor information in the query params.
   *
   * @param \Drupal\xapi\Plugin\Field\FieldType\XapiContentFileItem $item
   *   The xAPI content file item.
   *
   * @return \Drupal\Core\Url
   *   The fully-qualified launch URL.
   *
   * @throws \Drupal\xapi\XapiContentException
   *   If the item has an invalid activity URI.
   */
  public static function getLaunchUrl(XapiContentFileItem $item) {
    return Url::fromUri(
      $item->getActivityUri(),
      [
        'query' => self::getLaunchQueryParams($item),
        'attributes' => [
          'title' => $item->activity_name,
          'target' => '_blank',
        ],
      ]
    );
  }

  /**
   * Builds a set of query parameters for the launch URL.
   *
   * @param \Drupal\xapi\Plugin\Field\FieldType\XapiContentFileItem $item
   *   The xAPI content file item.
   *
   * @return array
   *   An array of key-value query params.
   */
  public static function getLaunchQueryParams(XapiContentFileItem $item) {
    $config = \Drupal::config('xapi.settings');
    if (empty($config->get('lrs_url')) || empty($item->activity_id)) {
      return [];
    }

    $user = User::load(\Drupal::currentUser()->id());
    $actor = XapiActor::createWithUser($user);

    // Retrieve the global URL value.
    global $base_url;

    // Following the query parameters from the xAPIWrapper
    // https://github.com/adlnet/xAPIWrapper
    return [
      // We define our own LRS routes to listen to xAPI statements.
      'endpoint' => $base_url . '/lrs/',
      // We don't necessarily need to use authentication for our own server.
      // However, if we don't set any authentication, Adapt uses "tom:1234".
      // In that case, Drupal tries to redirect to a login screen.
      // Setting to a non-useful string here causes Drupal to use cookie auth.
      'auth' => 'none',
      'actor' => json_encode($actor),
      'activity_id' => $item->activity_id,
      'activity_platform' => \Drupal::config('system.site')->get('name'),
    ];
  }

}
