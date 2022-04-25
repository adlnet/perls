<?php

namespace Drupal\recommender_additions;

use Drupal\recommender\Entity\RecommendationPluginScore;
use Drupal\views\Views;
use Drupal\views\ResultRow;
use Drupal\node\NodeInterface;
use Drupal\views\ViewExecutable;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\recommender\RecommendationEngineException;
use Drupal\recommender\Entity\RecommendationCandidate;
use Drupal\recommender\RecommendationEnginePluginBase;

/**
 * Provides the base logic for using a view as the source for recommendations.
 *
 * Each implementation must provide a view ID (string) and a way to
 * score each candidate. There are traits (such as `ScoringFieldTrait`)
 * that can be applied to an implementation to make it easy to get a score.
 *
 * By default, this assumes that the entity representing each result row
 * should also be considered the candidate. That won't be the case if node is
 * not the base table of the view or if the desired candidate is based on
 * a relationship. In those cases, implementations should override
 * `getRowCandidate` to direct the plugin to the intended node.
 */
abstract class ViewsCandidateSourceBase extends RecommendationEnginePluginBase {

  /**
   * A render service to use for rendering view results.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The token replacement service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Contains the current context of the plugin while it is being executed.
   *
   * While generating candidates, it will contain a reference to the user
   * recieving recommendations and the current candidate being processed.
   *
   * Used for token replacement when generating a recommendation reason.
   *
   * @var array
   */
  protected $currentContext = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('Recommendation Engine Plugin - ' . $plugin_id),
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('entity_type.manager')
    );

    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->renderer = $container->get('renderer');
    $instance->token = $container->get('token');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $form['recommendation_reason_template']['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['node', 'user'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function generateCandidates(AccountInterface $user) {
    $nodes = [];
    $view = $this->getView($user);
    $this->currentContext += [
      'user' => $user,
      'view' => $view,
    ];

    $this->renderer->executeInRenderContext(new RenderContext(), function () use ($view, $user, &$nodes) {
      $view->execute();

      foreach ($view->result as $row) {
        $node = $this->getRowCandidate($view, $row);
        if (!$node) {
          continue;
        }

        $this->currentContext += ['node' => $node, 'row' => $row];

        $score = $this->getRowScore($view, $row);
        $this->updateOrCreateScoreEntity($user, $node->id(), $score, RecommendationPluginScore::STATUS_PROCESSING);
        $nodes[$node->id()] = $node;

        unset($this->currentContext['node']);
        unset($this->currentContext['row']);
      }
    });

    // Since we're done generating candidates, clear out the current context.
    $this->currentContext = [];

    return $nodes;
  }

  /**
   * Finds and configures the view to use for generating candidates.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user receiving recommendations.
   *
   * @return \Drupal\views\ViewExecutable
   *   The configured (but not yet executed) view.
   */
  protected function getView(AccountInterface $account): ViewExecutable {
    $view_id = $this->getViewId();
    $view = Views::getView($view_id);

    if (!$view) {
      throw new RecommendationEngineException("No view found with id $view_id");
    }

    if (($display_id = $this->getViewDisplayId())) {
      $view->setDisplay($display_id);
    }

    if (($args = $this->getViewArguments($account))) {
      $view->setArguments($args);
    }

    $view->setItemsPerPage($this->getNumberOfCandidates());

    return $view;
  }

  /**
   * Retrieve the ID of the view to use for generating candidates.
   *
   * @return string
   *   The view ID.
   */
  abstract protected function getViewId(): string;

  /**
   * Retrieve the ID of the display to use for generating candidates.
   *
   * @return string|null
   *   The display ID; or NULL to use the default display.
   */
  protected function getViewDisplayId(): ?string {
    return NULL;
  }

  /**
   * Arguments to pass to the view prior to generating candidates.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user receiving recommendations.
   *
   * @return array
   *   Arguments to pass to the view.
   */
  protected function getViewArguments(AccountInterface $account): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getRecommendationReason($langcode = NULL) {
    $reason = parent::getRecommendationReason();
    return $this->token->replace($reason, $this->currentContext);
  }

  /**
   * Retrieves the recommendation candidate for a row of the view result.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view being used to generate recommendations.
   * @param \Drupal\views\ResultRow $row
   *   A result row from executing the view.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The recommendation candidate; or NULL if the row should be skipped.
   */
  protected function getRowCandidate(ViewExecutable $view, ResultRow $row): ?NodeInterface {
    return $row->_entity;
  }

  /**
   * Retrieves the recommendation candidate score for a row of the view result.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view being used to generate recommendations.
   * @param \Drupal\views\ResultRow $row
   *   A result row from executing the view.
   *
   * @return float
   *   The score for the row.
   */
  abstract protected function getRowScore(ViewExecutable $view, ResultRow $row): float;

  /**
   * {@inheritdoc}
   */
  public function scoreCandidates(array $candidates, AccountInterface $user) {
    foreach ($candidates as $nid => $candidate) {
      if (!($candidate instanceof RecommendationCandidate)) {
        $this->logger->warning('This plugin only handle recommendation candidates, but this candidate is a @type type.', ['@type' => get_class($candidate)]);
        continue;
      }

      /** @var \Drupal\Core\Entity\EntityInterface $node */
      $node = $candidate->nid->entity;
      if ($node) {
        $score = $this->getScoreEntity($user->id(), $node->id());
        if ($score) {
          $candidate->scores[] = $score;
          $candidate->save();
        }
      }
      else {
        $this->logger->warning('We cannot load recommended content to candidate with @type type and @id candidate id', [
          '@type' => get_class($candidate),
          '@id' => $node->id(),
        ]);
      }
    }
  }

}
