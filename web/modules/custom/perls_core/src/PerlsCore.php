<?php

namespace Drupal\perls_core;

/**
 * Collection of helper methods.
 */
class PerlsCore {

  /**
   * Gives back of machine name of learning objects.
   *
   * @return string[]
   *   A list of learning objects.
   */
  public static function getLearningObjectList() {
    return [
      'learn_article',
      'learn_file',
      'learn_link',
      'learn_package',
    ];
  }

}
