<?php

namespace Drupal\recommender\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\recommender\RecommendationServiceInterface;
use Drupal\user\Entity\User;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A class to return update recommendations with ajax.
 */
class AjaxRecommendations extends ControllerBase {

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
   * The Recommendation Service.
   *
   * @var \Drupal\recommender\RecommendationServiceInterface
   */
  protected $recommendationService;

  /**
   * The current User.
   *
   * @var Drupal\user\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a ViewAjaxController object.
   *
   * @param \Drupal\user\AccountInterface $user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage for views.
   * @param \Drupal\views\ViewExecutableFactory $executable_factory
   *   The factory to load a view executable with.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\recommender\RecommendationServiceInterface $recommendation_service
   *   The recommendation service.
   */
  public function __construct(
      AccountInterface $user,
      EntityStorageInterface $storage,
      ViewExecutableFactory $executable_factory,
      RendererInterface $renderer,
      RecommendationServiceInterface $recommendation_service
      ) {
    $this->currentUser = $user;
    $this->storage = $storage;
    $this->executableFactory = $executable_factory;
    $this->renderer = $renderer;
    $this->recommendationService = $recommendation_service;
  }

  /**
   * Create function for dependency injection.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('view'),
      $container->get('views.executable'),
      $container->get('renderer'),
      $container->get('recommender.recommendation_service'),
    );
  }

  /**
   * Get latest recommendations in ajax form.
   */
  public function getAjaxRecommendations() {
    $response = new AjaxResponse();
    // First check if we need to build recommendations.
    /** @var Drupal/user/Entity/UserInterface. */
    $user = User::load($this->currentUser()->id());
    if (!$this->recommendationService->shouldBuildWithAjax() || $this->recommendationService->hasRecommendations($user)) {
      return $response;
    }
    // If we do build them.
    $this->recommendationService->buildUserRecommendations($user, 100, TRUE);

    // Get the updated view.
    $display_id = $this->recommendationService->getRecommendationAjaxViewDisplayId();
    if (!$entity = $this->storage->load($this->recommendationService->getRecommendationAjaxView())) {
      throw new NotFoundHttpException();
    }
    // Load the view.
    $view = $this->executableFactory->get($entity);
    $view->setDisplay($display_id);
    $view->initHandlers();

    // Create a render array for main search panel.
    $context = new RenderContext();
    $view_build = $this->renderer->executeInRenderContext($context, function () use ($view, $display_id) {
      return $view->preview($display_id, []);
    });

    $response->addCommand(new ReplaceCommand('.recommendation-ajax-view', $view_build));
    return $response;
  }

}
