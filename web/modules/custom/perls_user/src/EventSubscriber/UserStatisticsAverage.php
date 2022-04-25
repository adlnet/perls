<?php

namespace Drupal\perls_user\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\veracity_vql\Event\VqlPostExecuteEvent;
use Drupal\perls_learner_state\FlaggedUserStatistics;

/**
 * Adds an average line to VQL graph.
 */
class UserStatisticsAverage implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * FlaggedUserStatistics variable.
   *
   * @var \Drupal\perls_learner_state\FlaggedUserStatistics
   */
  protected $flaggedUserStatistics;

  /**
   * Constructor.
   *
   * @param \Drupal\perls_learner_state\FlaggedUserStatistics $flaggedUserStatistics
   *   The FlaggedUserStatistics service.
   */
  public function __construct(FlaggedUserStatistics $flaggedUserStatistics) {
    $this->flaggedUserStatistics = $flaggedUserStatistics;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      VqlPostExecuteEvent::EVENT_NAME => 'userAverageStatistics',
    ];
  }

  /**
   * Adds average line to the VQL graph.
   *
   * @param \Drupal\veracity_vql\Event\VqlPostExecuteEvent $event
   *   The Event.
   */
  public function userAverageStatistics(VqlPostExecuteEvent $event) {
    if (!empty($event->getResult())) {
      foreach ($event->getResult() as &$chart) {
        if (!isset($chart['chart'])) {
          continue;
        }

        // Getting the flagged user statistics.
        $flaggedUserStatistic = $this->flaggedUserStatistics->getFlaggedUserStatistics();

        $query = $event->getQuery();
        $value = 0;
        $variables =
          [
            '@label' => $this->t('Others'),
          ];

        // Setting the value per block.
        if (!empty($query['block_identifier'])) {
          switch ($query['block_identifier']) {
            case 'lo_viewed_week':
              $value = $flaggedUserStatistic['others_average_seen_count_lo_week'];
              $variables['@value'] = round($value);
              break;

            case 'lo_completed_week':
              $value = $flaggedUserStatistic['others_average_completed_count_week'];
              $variables['@value'] = round($value);
              break;

            case 'c_completed_month':
              $value = $flaggedUserStatistic['others_average_completed_count_course_month'];
              $variables['@value'] = round($value);
              break;

            case 'average_test_week':
              $value = $flaggedUserStatistic['others_average_result_avg_test_week'];
              $variables['@value'] = round($value) . '%';
              break;
          }
        }

        if (!$value) {
          continue;
        }

        $chart['chart']['yAxes'][0]['axisRanges'][] = [
          'value' => round($value),
          'grid' => [
            'above' => TRUE,
            'stroke' => '#797979',
            'strokeWidth' => 2,
            'strokeOpacity' => 1,
          ],
          'label' => [
            'inside' => TRUE,
            'text' => $this->t("@label (@value)", $variables),
            'fill' => '#797979',
            'align' => 'left',
            'verticalCenter' => 'bottom',
          ],
        ];
      }
    }
  }

}
