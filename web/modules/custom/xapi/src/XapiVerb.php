<?php

namespace Drupal\xapi;

/**
 * This class represents a verb object in xApi.
 */
class XapiVerb implements \JsonSerializable {

  /**
   * Contains Xapi verb url.
   *
   * @var string
   */
  protected $id;

  /**
   * Contains a verb display name.
   *
   * @var array
   */
  protected $display = [];

  /**
   * XapiVerb Voided.
   *
   * @returns static instance of voided verb.
   */
  public static function voided() {
    return new self("http://adlnet.gov/expapi/verbs/voided", "voided");
  }

  /**
   * XapiVerb completed.
   *
   * @returns static instance of completed verb.
   */
  public static function completed() {
    return new self("http://adlnet.gov/expapi/verbs/completed", "completed");
  }

  /**
   * XapiVerb failed.
   *
   * @returns static instance of failed verb.
   */
  public static function failed() {
    return new self("http://adlnet.gov/expapi/verbs/failed", "failed");
  }

  /**
   * XapiVerb passed.
   *
   * @returns static instance of passed verb.
   */
  public static function passed() {
    return new self("http://adlnet.gov/expapi/verbs/passed", "passed");
  }

  /**
   * XapiVerb launched.
   *
   * @returns static instance of launched verb.
   */
  public static function launched() {
    return new self("http://adlnet.gov/expapi/verbs/launched", "launched");
  }

  /**
   * XapiVerb experienced.
   *
   * @returns static instance of experienced verb.
   */
  public static function experienced() {
    return new self("http://adlnet.gov/expapi/verbs/experienced", "experienced");
  }

  /**
   * XapiVerb interacted.
   *
   * @returns static instance of interacted verb.
   */
  public static function interacted() {
    return new self("http://adlnet.gov/expapi/verbs/interacted", "interacted");
  }

  /**
   * XapiVerb answered.
   *
   * @returns static instance of answered verb.
   */
  public static function answered() {
    return new self("http://adlnet.gov/expapi/verbs/answered", "answered");
  }

  /**
   * XapiVerb asked.
   *
   * @returns static instance of asked verb.
   */
  public static function asked() {
    return new self("http://adlnet.gov/expapi/verbs/asked", "asked");
  }

  /**
   * XapiVerb attempted.
   *
   * @returns static instance of attempted verb.
   */
  public static function attempted() {
    return new self("http://adlnet.gov/expapi/verbs/attempted", "attempted");
  }

  /**
   * XapiVerb commented.
   *
   * @returns static instance of commented verb.
   */
  public static function commented() {
    return new self("http://adlnet.gov/expapi/verbs/commented", "commented on");
  }

  /**
   * XapiVerb responded.
   *
   * @returns static instance of responded verb.
   */
  public static function responded() {
    return new self("http://adlnet.gov/expapi/verbs/responded", "responded");
  }

  /**
   * XapiVerb attended.
   *
   * @returns static instance of attended verb.
   */
  public static function attended() {
    return new self("http://adlnet.gov/expapi/verbs/attended", "attended");
  }

  /**
   * XapiVerb defined.
   *
   * @returns static instance of defined verb.
   */
  public static function defined() {
    return new self("http://id.tincanapi.com/verb/defined", "defined");
  }

  /**
   * XapiVerb cancelled.
   *
   * @returns static instance of cancelled verb.
   */
  public static function cancelled() {
    return new self("http://activitystrea.ms/schema/1.0/cancel", "cancelled");
  }

  /**
   * XapiVerb constructor.
   *
   * @param string $id
   *   Corresponds to a Verb definition.
   *   Each Verb definition corresponds to the meaning of a Verb, not the word.
   * @param string $display
   *   The human readable representation of the Verb in one or more languages.
   *   This does not have any impact on the meaning of the Statement,
   *   but serves to give a human-readable display of the meaning
   *   already determined by the chosen Verb.
   * @param string $languageCode
   *   The language code for the verb display.
   */
  public function __construct(string $id, string $display = NULL, string $languageCode = 'en') {
    $this->id = $id;
    if ($display !== NULL) {
      $this->display[$languageCode] = $display;
    }
  }

  /**
   * Set the id property.
   *
   * @param string $id
   *   A verb url.
   *
   * @return XapiVerb
   *   This object itself.
   */
  public function setId(string $id): XapiVerb {
    $this->id = $id;
    return $this;
  }

  /**
   * Sets the verb display.
   *
   * @param string|array $display
   *   The display name for the verb.
   * @param string $languageCode
   *   The language code (defaults to English).
   *
   * @return XapiVerb
   *   The current XapiVerb builder.
   */
  public function setDisplay($display, string $languageCode = 'en'): XapiVerb {
    if (is_string($display)) {
      $this->display[$languageCode] = $display;
    }
    elseif (is_array($display)) {
      $this->display = $display;
    }
    else {
      throw new \InvalidArgumentException('The verb display must be either a string or array');
    }

    return $this;
  }

  /**
   * Gives back the id property.
   *
   * @return string
   *   Gives back the verb type.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * {@inheritDoc}
   */
  public function jsonSerialize() {
    $output = [
      'id' => $this->id,
    ];

    if (!empty($this->display)) {
      $output += [
        'display' => $this->display,
      ];
    }

    return $output;
  }

}
