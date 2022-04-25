<?php

namespace Drupal\notifications\TwigExtension;

/**
 * Decode json object.
 */
class JsonDecode extends \Twig_Extension {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'notifications_jsondecode';
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new \Twig_SimpleFunction(
        'notifications_jsondecode',
        [$this, 'jsonDecode'],
        ['is_safe' => ['html']]
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new \Twig_SimpleFilter(
        'notifications_jsondecode',
        [$this, 'jsonDecode'],
        ['is_safe' => ['html']]
      ),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @param string $string
   *   Is the string to replace.
   */
  public function jsonDecode(string $string) {
    return json_decode($string, TRUE);
  }

}
