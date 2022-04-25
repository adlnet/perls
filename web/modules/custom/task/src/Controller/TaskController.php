<?php

namespace Drupal\task\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\task\Entity\TaskInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TaskController.
 *
 *  Returns responses for task routes.
 */
class TaskController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Displays a task revision.
   *
   * @param int $task_revision
   *   The task revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($task_revision) {
    $task = $this->entityTypeManager()->getStorage('task')
      ->loadRevision($task_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('task');

    return $view_builder->view($task);
  }

  /**
   * Page title callback for a task revision.
   *
   * @param int $task_revision
   *   The task revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($task_revision) {
    $task = $this->entityTypeManager()->getStorage('task')
      ->loadRevision($task_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $task->label(),
      '%date' => $this->dateFormatter->format($task->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a task.
   *
   * @param \Drupal\task\Entity\TaskInterface $task
   *   A task object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(TaskInterface $task) {
    $account = $this->currentUser();
    $task_storage = $this->entityTypeManager()->getStorage('task');

    $langcode = $task->language()->getId();
    $langname = $task->language()->getName();
    $languages = $task->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title',
      [
        '@langname' => $langname,
        '%title' => $task->label(),
      ]) : $this->t('Revisions for %title', ['%title' => $task->label()]);

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all task revisions") || $account->hasPermission('administer task entities')));
    $delete_permission = (($account->hasPermission("delete all task revisions") || $account->hasPermission('administer task entities')));

    $rows = [];

    $vids = $task_storage->revisionIds($task);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\task\Entity\TaskInterface $revision */
      $revision = $task_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $task->getRevisionId()) {
          /** @var \Drupal\Core\Url $url */
          $url = Url::fromRoute('entity.task.revision', [
            'task' => $task->id(),
            'task_revision' => $vid,
          ]);
          $link = Link::fromTextAndUrl($date, $url)->toRenderable();
        }
        else {
          $link = $task->toLink($date)->toRenderable();
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $this->renderer->render($link),
              'username' => $this->renderer->renderPlain($username),
              'message' => [
                '#markup' => $revision->getRevisionLogMessage(),
                '#allowed_tags' => Xss::getHtmlTagList(),
              ],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => $has_translations ?
              Url::fromRoute('entity.task.translation_revert', [
                'task' => $task->id(),
                'task_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.task.revision_revert', [
                'task' => $task->id(),
                'task_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.task.revision_delete', [
                'task' => $task->id(),
                'task_revision' => $vid,
              ]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['task_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
