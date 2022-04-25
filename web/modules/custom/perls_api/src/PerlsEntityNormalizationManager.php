<?php

namespace Drupal\perls_api;

use Drupal\entity_normalization\EntityNormalizationManager;

/**
 * Custom normalization manager.
 */
class PerlsEntityNormalizationManager extends EntityNormalizationManager {

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    'format' => NULL,
    'weight' => 0,
    'class' => PerlsEntityConfig::class,
  ];

}
