<?php

namespace Drupal\video_embed_ustudio\Plugin\video_embed_field\Provider;

use Drupal\Component\Serialization\Json;
use Drupal\video_embed_field\ProviderPluginBase;
use GuzzleHttp\Exception\RequestException;

/**
 * A UStudio provider plugin for video embed field.
 *
 * @VideoEmbedProvider(
 *   id = "ustudio",
 *   title = @Translation("uStudio")
 * )
 */
class Ustudio extends ProviderPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function getIdFromInput($input) {
    return static::getUrlMetadata($input, 'video');
  }

  /**
   * {@inheritdoc}
   */
  public function renderEmbedCode($width, $height, $autoplay) {
    $iframe = [
      '#type' => 'video_embed_iframe',
      '#provider' => 'ustudio',
      '#url' => sprintf('https://app.ustudio.com/embed/%s/%s', $this->getDestination(), $this->getVideo()),
      '#query' => [],
      '#attributes' => [
        'width' => $width,
        'height' => $height,
        'frameborder' => '0',
        'allowfullscreen' => 'true',
      ],
    ];
    return $iframe;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteThumbnailUrl() {
    try {
      $response = $this->httpClient->request(
        'GET',
        sprintf('https://app.ustudio.com/embed/%s/%s/config.json', $this->getDestination(), $this->getVideo()),
        ['timeout' => 5]
      );
      if ($response->getStatusCode() === 200) {
        $data = Json::decode($response->getBody()->getContents());
      }
    }
    catch (RequestException $e) {
      return FALSE;
    }
    if (isset($data) && isset($data['videos'][0]['images'][0]['image_url'])) {
      return $data['videos'][0]['images'][0]['image_url'];
    }
    return FALSE;
  }

  /**
   * Get the player ID from the input URL.
   *
   * @return string
   *   The video player ID.
   */
  protected function getVideo() {
    return static::getUrlMetadata($this->getInput(), 'video');
  }

  /**
   * Get the player name from the input URL.
   *
   * @return string
   *   The video player name.
   */
  protected function getDestination() {
    return static::getUrlMetadata($this->getInput(), 'destination');
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
    preg_match('/^((http|https):){0,1}\/\/(www\.){0,1}(embed|app)\.ustudio\.com\/embed\/(?<destination>[^=&?\/\r\n]{10,12})\/(?<video>[^=&?\/\r\n]{10,12}).*$/i', $input, $matches);
    return isset($matches[$metadata]) ? $matches[$metadata] : FALSE;
  }

}
