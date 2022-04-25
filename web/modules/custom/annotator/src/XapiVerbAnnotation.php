<?php

namespace Drupal\annotator;

use Drupal\xapi\XapiVerb;

/**
 * Defines an XapiVerb for annotations.
 */
class XapiVerbAnnotation extends XapiVerb {

  /**
   * XapiVerb Annotated.
   *
   * @returns static instance of annotated verb.
   */
  public static function annotated() {
    return new self("http://risc-inc.com/annotator/verbs/annotated", "annotated");
  }

}
