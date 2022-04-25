<?php

namespace Drupal\notifications\TwigExtension;

/**
 * Convert the camelcase string to space separated string.
 */
class CamelToSpace extends \Twig_Extension {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'notifications_cameltospace';
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new \Twig_SimpleFunction(
        'notifications_cameltospace',
        [$this, 'camelToSpace'],
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
        'notifications_cameltospace',
        [$this, 'camelToSpace'],
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
  public function camelToSpace(string $string) {
    $pattern = '/(([A-Z]{1}))/';
    return trim(preg_replace_callback(
            $pattern,
            function ($matches) {
              return " " . $matches[0];
            },
            $string
        ));
  }

}
