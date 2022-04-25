<?php

// @codingStandardsIgnoreFile

/**
 * Any non-dev specific settings go here.
 * Only settings that affect the PROD or STAGE environment ONLY should go here.
 *
 * This file should be used with the environment variable:
 * PROJECT_ENV=prod
 * This file should be copied to settings.local.php in PROD and STAGE during deployment.
 */
 $settings['reverse_proxy'] = TRUE;
 $settings['reverse_proxy_addresses'] = ['10.0.0.0/16'];


/**
 * Set Private file path:
 */
$settings['file_private_path'] = '../private';
$settings['php_storage']['twig']['directory'] = 'sites/default/files/php/twig';

if (!empty(getenv('REDIS_HOST'))) {
  $settings['container_yamls'][] = DRUPAL_ROOT . '/sites/prod.services.yml';
  $settings['cache']['default'] = 'cache.backend.redis';
  $config['cache_class_cache'] = 'Redis_Cache';
  $config['redis_client_interface'] = 'PhpRedis';
  $settings['redis.connection']['interface'] = 'PhpRedis';
  $settings['redis.connection']['host'] = getenv('REDIS_HOST');
  $settings['redis.connection']['port'] = '6379';
}
if (php_sapi_name() != "cli") {
  $settings['container_yamls'][] = 'sites/monolog.services.yml';
}
