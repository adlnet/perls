<?php

namespace Drupal\perls_adaptive_content\Plugin\AdaptiveContent\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides adaptive learning plugin for each difficulty level.
 *
 * @see Drupal\perls_adaptive_content\Plugin\AdaptiveContent\DifficultyAdaptiveContent
 */
class DifficultyAdaptiveContentDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The term storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $termStorage;

  /**
   * Constructs new DifficultyAdaptiveContentDeriver.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $term_storage
   *   The term storage.
   */
  public function __construct(EntityStorageInterface $term_storage) {
    $this->termStorage = $term_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')->getStorage('taxonomy_term')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $terms = $this->termStorage->loadByProperties(['vid' => 'difficulty']);
    foreach ($terms as $term) {
      $this->derivatives[$term->uuid()] = $base_plugin_definition;
      $this->derivatives[$term->uuid()]['label'] = t('Skip @term and less difficult content', ['@term' => $term->label()]);
      $this->derivatives[$term->uuid()]['extra_data'] = [
        'term_id' => $term->id(),
        'term_weight' => $term->getWeight(),
      ];
    }
    return $this->derivatives;
  }

}
