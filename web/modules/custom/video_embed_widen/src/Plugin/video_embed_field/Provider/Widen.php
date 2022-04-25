<?php

namespace Drupal\video_embed_widen\Plugin\video_embed_field\Provider;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\video_embed_field\ProviderPluginBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Widen provider plugin for video embed field.
 *
 * @VideoEmbedProvider(
 *   id = "widen",
 *   title = @Translation("Widen")
 * )
 */
class Widen extends ProviderPluginBase {

  /**
   * Cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   *   Cache.
   */
  protected $cache;

  /**
   * Create a Widen video provider plugin with the given input.
   *
   * @param array $configuration
   *   The configuration of the plugin.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An HTTP client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache.
   *
   * @throws \Exception
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ClientInterface $http_client, CacheBackendInterface $cache) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $http_client);
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('cache.default')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getIdFromInput($input) {
    return static::getUrlMetadata($input, 'video');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function renderEmbedCode($width, $height, $autoplay) {
    // If proper embed URL can not be formed, use the input url.
    $embed_url = $this->getInput();

    // Attempt to get the video external asset id from json object in HTML.
    $asset_id = $this->getAssetIdFromInput();
    // If empty, attempt to get the video asset id from thumbnail url.
    if (empty($asset_id)) {
      $asset_id = $this->getAssetIdFromThumbnail();
    }
    // If Asset ID is present, form the embed url.
    if (!empty($asset_id)) {
      $embed_url = sprintf('%s/view/video/%s/%s', $this->getCdnBaseUrl(), $asset_id, $this->getVideoAlias());
    }

    return [
      '#type' => 'video_embed_iframe',
      '#provider' => 'widen',
      '#url' => $embed_url,
      '#query' => [],
      '#attributes' => [
        'width' => $width,
        'height' => $height,
        'frameborder' => '0',
        'allowfullscreen' => 'true',
      ],
    ];
  }

  /**
   * Get absolute URL of the video thumbnail image.
   *
   * @return bool|string
   *   URL of the video thumbnail image.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getRemoteThumbnailUrl() {
    $thumbnail_path = $this->getThumbnailPath();
    if (!empty($thumbnail_path) && !empty($host = $this->getCdnBaseUrl())) {
      return $host . $thumbnail_path;
    }
    return FALSE;
  }

  /**
   * Get relative path of the video thumbnail image.
   *
   * @return string|bool
   *   Returns relative image path, false if request is unsuccessful.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function getThumbnailPath() {
    $public_video_id = static::getUrlMetadata($this->getInput(), 'video');
    if (!empty($public_video_id)) {
      $cid = 'video_embed_widen:thumbnail_' . $public_video_id;
      if ($cache = $this->cache->get($cid)) {
        return $cache->data;
      }
      else {
        try {
          $response = $this->httpClient->request(
            'GET',
            $this->getInput(),
            ['timeout' => 5]
          );
          if ($response->getStatusCode() === 200) {
            $data = Html::load(($response->getBody()->getContents()));
            foreach ($data->getElementsByTagName('meta') as $meta) {
              $property = $meta->getAttribute('property');
              if (!empty($property) && $property === 'og:image') {
                $thumbnail_path = $meta->getAttribute('content');
                if (!empty($thumbnail_path)) {
                  // Save the value in permanent cache to avoid re-requesting.
                  $this->cache->set($cid, $thumbnail_path);
                  return $thumbnail_path;
                }
              }
            }
          }
        }
        catch (RequestException $e) {
          return FALSE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Get external video asset id from json object in the received HTML.
   *
   * @return string|bool
   *   Returns asset id if it's found, false otherwise.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function getAssetIdFromInput() {
    $public_video_id = static::getUrlMetadata($this->getInput(), 'video');
    if (!empty($public_video_id)) {
      $cid = 'video_embed_widen:asset_id_' . $public_video_id;
      if ($cache = $this->cache->get($cid)) {
        return $cache->data;
      }
      else {
        try {
          $response = $this->httpClient
            ->request('GET', $this->getInput(), ['timeout' => 5]);
          if ($response->getStatusCode() === 200) {
            $data = Html::load(($response->getBody()->getContents()));
            // Access the bootstrap json data inside the script tag in the html.
            $element = $data->getElementById('bootstrap-data');
            if (!empty($element) && !empty($bootstrapData = $element->nodeValue)) {
              // Remove javaScript variable name and semi-colon from the string
              // before decoding json.
              $bootstrapData = rtrim(ltrim($bootstrapData, 'window.bootstrapData ='), ';');
              $bootstrapJson = json_decode($bootstrapData);
              $asset_id = $bootstrapJson->assetExternalId ?? '';
              // Save the value in permanent cache to avoid re-requesting.
              $this->cache->set($cid, $asset_id);
              return $asset_id;
            }
          }
        }
        catch (RequestException $e) {
          return FALSE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Get the CDN baseURL from the input URL.
   *
   * @return string
   *   The service base URL.
   */
  protected function getCdnBaseUrl() {
    return static::getUrlMetadata($this->getInput(), 'baseurl');
  }

  /**
   * Get the player ID from the input URL.
   *
   * @return string
   *   The video player ID.
   */
  protected function getAssetIdFromThumbnail() {
    // Widen uses different unique identifiers for videos in public and
    // private urls. Fetch thumbnail path to get the private video ID.
    $thumbnail_url = $this->getThumbnailPath();
    if (preg_match("/^\/(content)\/(?<video>[^=&?\/\r\n]{10,}).*$/i", $thumbnail_url, $matches)) {
      return $matches['video'] ?? FALSE;
    }
  }

  /**
   * Get the video URL alias from the input URL.
   *
   * @return string
   *   The video URL alias.
   */
  protected function getVideoAlias() {
    return static::getUrlMetadata($this->getInput(), 'alias');
  }

  /**
   * Extract metadata from the input URL.
   *
   * @param string $input
   *   Input a user would enter into a video field.
   * @param string $metadata
   *   The metadata matching the regex capture group to get from the URL.
   *
   * @return string|bool
   *   The metadata or FALSE on failure.
   */
  protected static function getUrlMetadata($input, $metadata) {
    // The video url has 3 parts, Detect CDN host,  video short code and alias.
    // alias. e.g. https://[client].widen.net/s/[short-code]/[video-name-alias]
    preg_match('/^(?<baseurl>((http|https):){0,1}\/\/(www\.){0,1}(([^=&?\/\r\n]{3,})\.widen\.net))\/s\/(?<video>[^=&?\/\r\n]{10,12})\/(?<alias>[^=&?\/\r\n]{10,}).*$/i', $input, $matches);
    return $matches[$metadata] ?? FALSE;
  }

}
