<?php

namespace Drupal\perls_search\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A simple form to create a search box for dashboard.
 */
class PerlsDashboardSearchForm extends FormBase {

  /**
   * The entity storage for views.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The factory to load a view executable with.
   *
   * @var \Drupal\views\ViewExecutableFactory
   */
  protected $executableFactory;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a ViewAjaxController object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage for views.
   * @param \Drupal\views\ViewExecutableFactory $executable_factory
   *   The factory to load a view executable with.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(EntityStorageInterface $storage, ViewExecutableFactory $executable_factory, RendererInterface $renderer) {
    $this->storage = $storage;
    $this->executableFactory = $executable_factory;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('view'),
      $container->get('views.executable'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'perls_dashboard_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $build_params = NULL) {
    $form_state->set('build_params', $build_params);

    $form['search_api_fulltext'] = [
      '#type' => 'textfield',
    ];

    $form['search_api_fulltext']['#attributes']['placeholder'] = t('Search...');
    $form['#attributes']['class'][] = 'form-type-search';
    $form['search_api_fulltext']['#attributes']['class'][] = 'frontpage-search';
    $form['search_api_fulltext']['#attributes']['class'][] = 'form-search';
    $form['search_api_fulltext']['#attributes']['autocomplete'] = 'off';

    $form['actions'] = [
      '#type' => 'button',
      '#value' => $this->t('Search'),
      '#ajax' => [
        'callback' => '::replaceWithSearch',
        'event' => 'click',
        'progress' => [
          'type' => 'fullscreen',
          'message' => NULL,
        ],
      ],
      '#attributes' => [
        'class' => [
          'views-auto-submit-click',
          'js-hide',
        ],
      ],
    ];

    $form['#attached']['library'][] = 'ctools_views/autosubmit';
    $form['#attached']['library'][] = 'perls_search/perls_search-lib';
    $form['#attributes']['class'][] = 'views-auto-submit-full-form';

    return $form;
  }

  /**
   * This ajax callback generates a search page to replace current page.
   */
  public function replaceWithSearch(array $form, FormStateInterface $form_state) {
    $build_params = $form_state->get('build_params');
    // Value from search field on dashboard.
    $query = $form_state->cleanValues()->getValues();

    $args = [];
    // The main display for search.
    $display_id = $build_params['display_id'];
    if (!$entity = $this->storage->load($build_params['views_id'])) {
      throw new NotFoundHttpException();
    }
    // Load the view.
    $view = $this->executableFactory->get($entity);
    // Pass the search value to view.
    $view->setExposedInput($query);
    $view->setDisplay($display_id);
    $view->initHandlers();

    // The ajax response object we are going to return.
    $response = new AjaxResponse();

    // Create a render array for main search panel.
    $context = new RenderContext();
    $main_search_panel = $this->renderer->executeInRenderContext($context, function () use ($view, $display_id, $args) {
      $preview = $view->preview($display_id, $args);
      $preview['#attached']['drupalSettings']['perls_search'] = [
        'search_path' => $view->getPath(),
      ];
      return $preview;
    });
    if (!$context->isEmpty()) {
      $bubbleable_metadata = $context->pop();
      BubbleableMetadata::createFromRenderArray($main_search_panel)
        ->merge($bubbleable_metadata)
        ->applyTo($main_search_panel);
    }

    // Wrap our three components in a build array.
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['l-content'],
      ],
      '#attached' => [
        'library' => [
          'perls_search/perls_search-lib',
        ],
      ],
      'view' => $main_search_panel,
    ];

    // Command to run before page is replaced.
    $response->addCommand(new InvokeCommand(NULL, 'before_replace', []));
    // Command to replace entire content with search page.
    $response->addCommand(new ReplaceCommand(".l-content", $build));
    // Command to call javascript to refocus search box.
    $response->addCommand(new InvokeCommand(NULL, 'refocus_search', []));
    $response->addCommand(new InvokeCommand(NULL, 'after_dashboard_search', []));
    return $response;

  }

  /**
   * Submitting the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // We currently do nothing when submitting this form. We might want to add
    // functionality here in case javascript is disabled.
  }

}
