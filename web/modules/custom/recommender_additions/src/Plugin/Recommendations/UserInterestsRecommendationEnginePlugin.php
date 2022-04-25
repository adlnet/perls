<?php

namespace Drupal\recommender_additions\Plugin\Recommendations;

use Drupal\Core\Render\RenderContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\recommender_additions\ViewsCandidateSourceBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Overrides the user_interests_recommendation_plugin.
 */
class UserInterestsRecommendationEnginePlugin extends ViewsCandidateSourceBase {
  use ResetsPastCandidatesTrait;
  use ScoringViewTrait;

  /**
   * {@inheritdoc}
   */
  protected function getViewId(): string {
    return 'uncompleted_topics_contents';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRowScore(ViewExecutable $view, ResultRow $row): float {
    return mt_rand(750, 1000) / 1000;
  }

  /**
   * {@inheritdoc}
   */
  protected function getScoringViewId(): string {
    return 'user_favorite_topics';
  }

  /**
   * {@inheritdoc}
   */
  protected function getForeignKeyFieldName(): string {
    return 'tid';
  }

  /**
   * {@inheritDoc}
   */
  protected function getViewArguments(AccountInterface $account): array {
    $view = Views::getView($this->getScoringViewId());
    $view->setArguments([$account->id()]);

    $this->renderer->executeInRenderContext(new RenderContext(), function () use ($view) {
      $view->execute();

      $this->scores = array_reduce($view->result, function ($scores, $row) use ($view) {
        $field_values = $this->getRowFieldValues($view, $row);
        if (($foreign_key_field_id = $this->findFieldId($view, $this->getForeignKeyFieldName()))) {
          $foreign_key = (string) $field_values[$foreign_key_field_id];
          $scores[$foreign_key] = $this->getScore($view, $row);
        }

        return $scores;
      }, []);
    });

    return [
      implode(',', array_keys($this->scores)),
    ];
  }

}
