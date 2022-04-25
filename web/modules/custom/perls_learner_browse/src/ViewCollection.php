<?php

namespace Drupal\perls_learner_browse;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * A collection of views.
 */
class ViewCollection implements CacheableDependencyInterface {
  /**
   * The views in this collection.
   *
   * @var \Drupal\views\ViewExecutable[]
   */
  protected $views = [];

  /**
   * Whether the rendered outputs hides empty results.
   *
   * @var bool
   */
  protected $hidesEmptyViews;

  /**
   * The number of items to query from each view.
   *
   * @var int
   */
  protected $itemsPerView;

  /**
   * Caching metadata.
   *
   * @var \Drupal\Core\Cache\CacheableMetadata
   */
  protected $cacheMetadata;

  /**
   * Creates a new ViewCollection.
   *
   * @param int $itemsPerView
   *   The number of items to query from each view.
   * @param bool $hidesEmptyViews
   *   Whether to hide empty views in rendered outputs.
   */
  public function __construct($itemsPerView = 10, $hidesEmptyViews = TRUE) {
    $this->itemsPerView = $itemsPerView;
    $this->hidesEmptyViews = $hidesEmptyViews;

    $this->cacheMetadata = new CacheableMetadata();

    // Assume that this collection is unique per user.
    // At some point, it might make more sense for the caller to clarify
    // the context of the views it is adding.
    $this->cacheMetadata->addCacheContexts(['user']);
  }

  /**
   * Add a view to the collection based on view ID.
   *
   * @param string $viewId
   *   The view ID.
   * @param array $args
   *   An optional set of arguments to pass to the view.
   * @param string $displayId
   *   Optionally, specify a specific display to use.
   *
   * @return ViewCollection
   *   The called object.
   */
  public function addViewById($viewId, array $args = NULL, $displayId = NULL) {
    $view = Views::getView($viewId);

    if (!$view) {
      trigger_error(new FormattableMarkup('addViewById() called with invalid view ID "@id".', ['@id' => $viewId]), E_USER_WARNING);
      return $this;
    }

    if ($displayId) {
      $view->setDisplay($displayId);
    }

    if ($args) {
      $view->setArguments($args);
    }
    return $this->addView($view);
  }

  /**
   * Add a view to the collection.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view to add.
   *
   * @return ViewCollection
   *   The called object.
   */
  public function addView(ViewExecutable $view) {
    if (!$view) {
      throw new \Exception('A view must be defined');
    }

    $view->setItemsPerPage($this->itemsPerView);

    $view->initDisplay();

    // Sorry--we can't support exposed filters...yet.
    foreach ($view->display_handler->getHandlers('filter') as $key => $handler) {
      if ($handler->isExposed()) {
        unset($view->display_handler->handlers['filter'][$key]);
      }
    }

    $view->preExecute();

    // Some views may start the render context too early which interferes
    // with our ability to cache our own response.
    // (e.g. `\Drupal\entity\QueryAccess\EntityQueryAlter` starts the render
    // context whenever it alters a query--which may be frequent).
    // This is generally only an issue if we want to return the response via a
    // REST resource because it does not allow cacheable metadata to bubble
    // from the render context (because it's returning JSON).
    // To handle this, we execute the view within a custom render context and
    // bubble any caching data from the context to our own cache metadata.
    $renderer = \Drupal::service('renderer');
    $context = new RenderContext();
    $renderer->executeInRenderContext($context, function () use ($view) {
      $view->execute();
    });

    // If the render context is no longer empty, then we need to take
    // any caching data that was added and apply it to our own cache metadata.
    if (!$context->isEmpty()) {
      $this->cacheMetadata->addCacheableDependency($context->pop());
    }

    $view->pager = NULL;

    $this->views[] = $view;

    // Because the view arguments are not part of the request URL,
    // they need to be manually added to the cache key for each view.
    $view->element['#cache']['keys'] = $view->args;

    // Collates caching data from the view into this collection.
    $this->cacheMetadata->addCacheTags($view->getCacheTags());

    return $this;
  }

  /**
   * Retrieves the number of rows (views) in our collection.
   *
   * This will skip counting empty rows if `hidesEmptyViews` is `TRUE`.
   *
   * @return int
   *   The number of rows in the collection.
   */
  public function rowCount() {
    $count = 0;

    foreach ($this->views as $view) {
      if ($this->hidesEmptyViews && count($view->result) === 0) {
        continue;
      }

      $count++;
    }

    return $count;
  }

  /**
   * Returns the entities from the result of each view in the collection.
   *
   * Results are grouped in associative arrays by the view providing the result.
   *
   * The output of this method is cached.
   *
   * @return array
   *   An array suitable for serialization with the entities from each view.
   *
   *   The array contains associative arrays with the following keys:
   *    * 'name': The name of the group.
   *    * 'url': An optional URL to retrieve more content.
   *    * 'content': An array of entities in the group.
   */
  public function groupedResults() {
    $output = [];
    foreach ($this->views as $view) {
      if ($this->hidesEmptyViews && count($view->result) === 0) {
        continue;
      }

      $entities = self::getResultEntities($view);
      $restDisplayId = self::getRestExportDisplayId($view);

      if ($restDisplayId) {
        $url = $view->getUrl(NULL, $restDisplayId);
      }
      else {
        $url = $view->getUrl();
      }

      $title = $view->getTitle();
      if (\Drupal::request()->getRequestFormat() === 'json') {
        $title = Html::decodeEntities($title);
      }

      $output[] = [
        'name' => $title,
        'url' => $url ? $url->toString(TRUE)->getGeneratedUrl() : NULL,
        'content' => $entities,
      ];
    }

    return $output;
  }

  /**
   * Builds a render array containing the output of each view in the collection.
   *
   * @return array
   *   Render array for the collection.
   */
  public function build() {
    $output = [];

    foreach ($this->views as $view) {
      if ($this->hidesEmptyViews && count($view->result) === 0) {
        continue;
      }

      $output[] = [
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $view->getTitle(),
        ],
        'content' => $view->buildRenderable(),
      ];
    }

    // Add caching information.
    if (count($output) > 0) {
      $output['#cache'] = [
        'keys' => $this->getCacheKeys(),
      ];
      $this->cacheMetadata->applyTo($output);
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->cacheMetadata->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->cacheMetadata->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->cacheMetadata->getCacheMaxAge();
  }

  /**
   * Retrieves the cache keys for this view collection.
   *
   * @return string[]
   *   An array of cache keys.
   */
  protected function getCacheKeys() {
    $keys = ['views_collection'];

    foreach ($this->views as $view) {
      $keys[] = $view->id();
      $keys[] = $view->current_display;

      if (!empty($view->element) && !empty($view->element['#cache']) && is_array($view->element['#cache']['keys'])) {
        $keys = array_merge($keys, $view->element['#cache']['keys']);
      }
    }

    return $keys;
  }

  /**
   * Retrieves the result entities from the view.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The executed view.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entities.
   */
  private static function getResultEntities(ViewExecutable $view) {
    if (empty($view->executed)) {
      return [];
    }

    $entities = [];

    foreach ($view->result as $row_index => $row) {
      $entities[] = $row->_entity;
    }

    return $entities;
  }

  /**
   * Finds the first Rest Export display on the specified view.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   *
   * @return string|null
   *   The display ID.
   */
  private static function getRestExportDisplayId(ViewExecutable $view) {
    foreach ($view->displayHandlers as $id => $plugin) {
      if ($plugin->getPluginId() === 'rest_export') {
        return $id;
      }
    }

    return NULL;
  }

}
