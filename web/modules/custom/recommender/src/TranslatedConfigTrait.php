<?php

namespace Drupal\recommender;

/**
 * A trait to aid getting translated config values.
 */
trait TranslatedConfigTrait {

  /**
   * Get translation of config setting or return default.
   */
  protected function translatedConfigOrDefault($config_id, $key, $langcode = NULL) {
    if (empty($langcode)) {
      // User current language if none selected.
      $langcode = \Drupal::service('language_manager')->getCurrentLanguage()->getId();
    }

    $translated_template_value = \Drupal::service('language_manager')->getLanguageConfigOverride($langcode, $config_id)->get($key);

    return $translated_template_value ?: \Drupal::service('config.factory')->get($config_id)->get($key);

  }

}
