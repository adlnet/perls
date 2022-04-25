<?php

namespace Drupal\prompts\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\prompts\Prompt;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class what all prompt block can use.
 */
abstract class PromptBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The prompt service.
   *
   * @var \Drupal\prompts\Prompt
   */
  protected $prompt;

  /**
   * PromptBlock constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\prompts\Prompt $prompt_service
   *   The prompt service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Prompt $prompt_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->prompt = $prompt_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('prompts.prompt')
    );
  }

}
