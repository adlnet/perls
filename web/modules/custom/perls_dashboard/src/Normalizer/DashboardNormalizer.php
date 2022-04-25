<?php

namespace Drupal\perls_dashboard\Normalizer;

use Drupal\layout_builder\Section;
use Drupal\page_manager\Entity\PageVariant;
use Drupal\page_manager\PageVariantInterface;
use Drupal\serialization\Normalizer\ConfigEntityNormalizer;
use Drupal\perls_dashboard\Plugin\rest\CacheableNormalization;

/**
 * Normalize the page manager config of dashboard.
 */
class DashboardNormalizer extends ConfigEntityNormalizer {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    return $this->getLayoutData($object);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof PageVariant &&
    $data->getVariantPluginId() === 'layout_builder';
  }

  /**
   * Normalize a layout builder page.
   *
   * @param \Drupal\page_manager\PageVariantInterface $object
   *   A page manager variant, which is using layout builder.
   *
   * @return string
   *   The api response in json format.
   */
  protected function getLayoutData(PageVariantInterface $object) {
    $api_data = [];
    /** @var \Drupal\page_manager\Plugin\DisplayVariant\LayoutBuilderDisplayVariant $layout */
    $layout = $object->getVariantPlugin();
    $sections = $layout->getSections();
    $normalized_sections = [];
    foreach ($sections as $section) {
      $components = $section->getComponents();
      if (empty($components)) {
        continue;
      }
      $normalized_section = $this->getNormalizedSection($section);
      if (isset($normalized_section) && !empty($normalized_section->getNormalization()['blocks'])) {
        $api_data[] = $normalized_section->getNormalization();
        $normalized_sections[] = $normalized_section;
      }

    }
    $normalized_sections = CacheableNormalization::aggregate($normalized_sections);
    $normalized_sections->setNormalization($api_data);
    return $normalized_sections;
  }

  /**
   * Normalize the layout sections.
   *
   * @param \Drupal\layout_builder\Section $section
   *   The section what we need to normalize.
   *
   * @return \Drupal\perls_dashboard\Plugin\rest\CacheableNormalization|null
   *   The normalized sections.
   */
  public function getNormalizedSection(Section $section) {
    $components = $section->getComponents();
    $data = [];
    $data['layout'] = $section->getLayoutId();
    $normalized_components = [];
    foreach ($components as $component) {
      /** @var \Drupal\perls_dashboard\Plugin\rest\CacheableNormalization $normalized_component */
      $normalized_component = $this->serializer->normalize($component);
      if (isset($normalized_component) && !empty($normalized_component->getNormalization())) {
        $data['blocks'][] = $normalized_component->getNormalization();
        $normalized_components[] = $normalized_component;
      }
    }

    if (empty($normalized_components)) {
      return NULL;
    }
    $normalized_section = CacheableNormalization::aggregate($normalized_components);
    $normalized_section->setNormalization($data);
    return $normalized_section;

  }

}
