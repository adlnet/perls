<?php

namespace Drupal\perls_learner_state\Annotation;

use Drupal\Component\Annotation\AnnotationBase;
use Drupal\perls_xapi_reporting\PerlsXapiVerb;
use Drupal\xapi\XapiVerb as Verb;

/**
 * Defines an XapiVerb annotation object.
 *
 * @Annotation
 */
class XapiVerb extends AnnotationBase {

  /**
   * The plugin ID.
   *
   * When an annotation is given no key, 'value' is assumed by Doctrine.
   *
   * @var string
   */
  public $value;

  /**
   * Constructs a Plugin object.
   *
   * Builds up the plugin definition and invokes the get() method for any
   * classed annotations that were used.
   */
  public function __construct($values) {
    $this->value = $values['value'];
    $class = PerlsXapiVerb::class;
    if (isset($values['class']) && is_subclass_of($values['class'], Verb::class)) {
      $class = $values['class'];
    }
    $this->class = $class;
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function get() {
    return call_user_func(sprintf('%s::%s', $this->class, $this->value));
  }

}
