<?php

namespace Drupal\perls_learner_state\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Define an XapiState annotation object.
 *
 * @see \Drupal\perls_learner_state\Plugin\StateManager
 * @Annotation
 */
class XapiState extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * The verb, that we should set after a flagging event.
   *
   * @var \Drupal\perls_xapi_reporting\XapiVerb
   *
   * The object has two key, the url and verb_type.
   */
  public $addVerb;

  /**
   * The verb, that we should set after a flagging event.
   *
   * @var \Drupal\perls_xapi_reporting\XapiVerb
   *
   * The object has two key, the url and verb_type.
   */
  public $removeVerb;

  /**
   * Notify plugin when a xapi state matching add verb or remove verb is rx.
   *
   * @var bool
   */
  public $notifyOnXapi;

  /**
   * Flag machine name.
   *
   * @var string
   */
  public $flag;

}
