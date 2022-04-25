<?php

namespace Drupal\perls_api;

/**
 * This class collect function which we can use everywhere in the project.
 */
class PerlsHelper {

  /**
   * Gives back of machine name of learning objects.
   *
   * @return string[]
   *   A list of learning objects.
   */
  public function getLearningObjectList() {
    return [
      'learn_article',
      'learn_file',
      'learn_link',
      'learn_package',
    ];
  }

}
