<?php

namespace Drupal\perls_dashboard\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\layout_builder\SectionComponent;
use Drupal\rest\Plugin\views\display\RestExport;
use Drupal\serialization\Normalizer\ConfigEntityNormalizer;
use Drupal\perls_dashboard\Plugin\rest\CacheableNormalization;
use Drupal\views\Plugin\Block\ViewsBlock;
use Drupal\views\Plugin\views\row\EntityRow;
use Drupal\views\ViewExecutable;

/**
 * Normalizer of Layout block which contains a views block.
 */
class DashboardItemBlock extends ConfigEntityNormalizer {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $data = [];
    /** @var \Drupal\layout_builder\SectionComponent $object */
    $configuration = $object->toArray()['configuration'];
    $pluign = $object->getPlugin();

    if ($this->isViewResultEmpty($pluign->getViewExecutable())) {
      return NULL;
    }

    $data['name'] = $configuration['label'];
    $data['more_url'] = empty($configuration['more_url']) ? NULL : $configuration['more_url'];
    $cache = new CacheableMetadata();
    $this->getViewsData($pluign, $data, $cache);
    $this->getRestView($pluign->getViewExecutable(), $data);
    return new CacheableNormalization($cache, $data);
  }

  /**
   * Decides that views has result.
   *
   * @param \Drupal\views\ViewExecutable $views
   *   The block view.
   *
   * @return bool
   *   TRUE if the view has result otherwise FALSE.
   */
  protected function isViewResultEmpty(ViewExecutable $views): bool {
    if (!$views->built) {
      $views->execute();
    }
    return empty($views->result);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $data instanceof SectionComponent &&
      $data->getPlugin() instanceof ViewsBlock;
  }

  /**
   * Extract the necessary data which is needed in normalization.
   *
   * @param \Drupal\views\Plugin\Block\ViewsBlock $block
   *   A viewBlock object, which contains the CMS block.
   * @param array $api_data
   *   This will contain the normalized response.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collect the cache tags and context.
   */
  protected function getViewsData(ViewsBlock $block, array &$api_data, CacheableMetadata $cache) {
    $views = $block->getViewExecutable();
    // We need to call because lot of property is empty without it.
    if (!$views->built) {
      $views->build();
    }

    if (!isset($api_data['name']) || empty($api_data['name'])) {
      $api_data['name'] = $views->getTitle();
    }

    $api_data['entity'] = $views->getBaseEntityType()->id();
    if ($views->rowPlugin instanceof EntityRow) {
      $api_data['template'] = $views->rowPlugin->options['view_mode'];
    }
    // Retrieves cache tags from view.
    /** @var \Drupal\views\Plugin\views\cache\CachePluginBase $view_cache */
    // Currently we set only tags and max age because views only has these
    // two kind of caching solution.
    $view_cache = $views->display_handler->getPlugin('cache');
    $cache->setCacheTags($view_cache->getCacheTags());
    $cache->setCacheMaxAge($view_cache->getCacheMaxAge());
  }

  /**
   * Load the rest display of a views block which was set in layout builder.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param array $api_data
   *   This will contain the normalized response.
   */
  protected function getRestView(ViewExecutable $view, array &$api_data) {
    if (!empty($view->current_display) && strpos($view->current_display, '_block')) {
      $rest_display = sprintf('%s_rest', str_replace('_block', '', $view->current_display));
      if (!$view->setDisplay($rest_display)) {
        throw new \Exception(t('The REST display is missing in @id view', ['@id' => $view->id()]));
      }

      if (!$view->display_handler instanceof RestExport) {
        throw new \Exception(t('This is not a REST display:@id', ['@id' => $view->id()]));
      }

      $api_data['contents_url'] = sprintf('/%s', $view->display_handler->getOption('path'));
    }
  }

}
