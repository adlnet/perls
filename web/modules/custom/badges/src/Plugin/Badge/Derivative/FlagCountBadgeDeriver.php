<?php

namespace Drupal\badges\Plugin\Badge\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a badge count plugin for all flags in the system.
 *
 * @see Drupal\badges\Plugin\Badges\FlagCountBadge
 */
class FlagCountBadgeDeriver extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;
  /**
   * The flag Service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Constructs new FlagBadgeDeriver.
   *
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The term storage.
   */
  public function __construct(FlagServiceInterface $flag_service) {
    $this->flagService = $flag_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('flag')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $flags = $this->flagService->getAllFlags();
    foreach ($flags as $flag) {

      $this->derivatives[$flag->id()] = $base_plugin_definition;
      $this->derivatives[$flag->id()]['label'] = $this->t('@flag Count Badge', ['@flag' => $flag->label()]);
      $this->derivatives[$flag->id()]['description'] = $this->t('Badge will be awarded when a certain number of items are marked with the @flag flag.', ['@flag' => $flag->label()]);
      $this->derivatives[$flag->id()]['extra_data'] = [
        'flag' => $flag->id(),
      ];
    }
    return $this->derivatives;
  }

}
