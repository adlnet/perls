<?php

namespace Drupal\prompts\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\layout_builder\SectionComponent;
use Drupal\serialization\Normalizer\ConfigEntityNormalizer;
use Drupal\prompts\Plugin\Block\DashboardItemPromptBlock;
use Drupal\perls_dashboard\Plugin\rest\CacheableNormalization;
use Drupal\prompts\Prompt;

/**
 * Normalizer for Prompt tile block.
 */
class DashboardPromptTileBlockNormalizer extends ConfigEntityNormalizer {

  /**
   * Prompt service.
   *
   * @var \Drupal\prompts\Prompt
   */
  protected $prompt;

  /**
   * Normalize the webform submissions.
   *
   * @var \Drupal\prompts\Normalizer\WebformSubmissionNormalizer
   */
  protected $submissionNormalizer;

  /**
   * DashboardPromptTileBlockNormalizer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface $entity_type_repository
   *   The entity type repository.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\prompts\Prompt $prompt
   *   Prompt service.
   * @param \Drupal\prompts\Normalizer\WebformSubmissionNormalizer $webform_submission_normalizer
   *   Webform submission normalizer.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeRepositoryInterface $entity_type_repository,
    EntityFieldManagerInterface $entity_field_manager,
    Prompt $prompt,
    WebformSubmissionNormalizer $webform_submission_normalizer) {
    parent::__construct($entity_type_manager, $entity_type_repository, $entity_field_manager);
    $this->prompt = $prompt;
    $this->submissionNormalizer = $webform_submission_normalizer;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    /** @var \Drupal\prompts\Plugin\Block\DashboardItemPromptBlock $block */
    $block = $object->getPlugin();
    $data = NULL;

    if (!empty($block->getPromptService()->loadPrompts())) {
      $data = [
        'name' => t('Prompt'),
        'template' => 'simple_banner',
        'contents' => [
          'icon' => DashboardItemPromptBlock::getPromptIconPath(),
          'text' => $block->getConfiguration()['block_text'],
          'url' => '/prompts',
        ],
      ];
    }
    $cache = new CacheableMetadata();
    $cache->setCacheTags(['prompts']);
    return new CacheableNormalization($cache, $data);

  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof SectionComponent &&
      $data->getPlugin() instanceof DashboardItemPromptBlock;
  }

}
