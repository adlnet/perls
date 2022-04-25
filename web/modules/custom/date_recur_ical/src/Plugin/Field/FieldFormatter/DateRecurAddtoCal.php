<?php

namespace Drupal\date_recur_ical\Plugin\Field\FieldFormatter;

use Drupal\Core\Url;
use Drupal\date_recur\Plugin\Field\FieldFormatter\DateRecurBasicFormatter;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Add To Calendar button for the date_recur Dates.
 *
 * @FieldFormatter(
 *   id          = "formatter_date_recur_ical",
 *   label       = @Translation("iCal Formatter"),
 *   field_types = {
 *     "date_recur"
 *   }
 * )
 */
class DateRecurAddtoCal extends DateRecurBasicFormatter {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('date.formatter'),
      $container->get('entity_type.manager')->getStorage('date_format'),
      $container->get('entity_type.manager')->getStorage('date_recur_interpreter')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function viewItem(DateRecurItem $item, $maxOccurrences): array {
    // Title of the Event.
    $title = $item->getParent()->getEntity()->getTitle();
    // Description of the Event.
    $description = '';
    if (!empty($item->getParent()->getEntity()->get('field_description')->getValue()[0]['value'])) {
      $description = $item->getParent()->getEntity()->get('field_description')->getValue()[0]['value'];
    }
    // URL of the Event.
    $nodeUrl = Url::fromRoute('entity.node.canonical', ['node' => $item->getParent()->getEntity()->id()], ['absolute' => TRUE])->toString();

    $build['schedule_list'] = parent::viewItem($item, $maxOccurrences);
    // Start building the build array.
    $build['addtocalendar'] = [];
    $build['addtocalendar'] = [
      '#type' => 'button',
      '#value' => $this->t('Add to Calendar'),
      '#attributes' => [
        'class' => ['daterecurical-btn'],
        'data-daterecurical' => ['daterecurical_' . $item->getParent()->getName()],
        'title' => 'Add to Calendar',
      ],
    ];

    // Support for rrule settings.
    $byday = $occurance = [];
    $occurance['freq'] = '';
    if (!empty($item->rrule)) {
      $rrules = explode(';', $item->rrule);
      foreach ($rrules as $rrule) {
        $rule = explode('=', $rrule);
        if (strtolower($rule[0]) == 'until') {
          $occurance[strtolower($rule[0])] = date('Y-m-d\TH:i:s', strtotime($rule[1]));
        }
        else {
          $occurance[strtolower($rule[0])] = $rule[1];
        }
      }

      if (!empty($occurance['byday'])) {
        $byday = explode(',', $occurance['byday']);
      }
    }

    $info = [
      'start' => $item->start_date->format('Y-m-d\TH:i:s'),
      'end' => $item->end_date->format('Y-m-d\TH:i:s'),
      'title' => $title,
      'description' => $description,
      'location' => $nodeUrl,
      'rrule' => $occurance,
      'byday' => $byday,
    ];

    $build['#attached']['library'][] = 'date_recur_ical/date_recur';
    $build['#attached']['drupalSettings']['date_recur_ical']['daterecurical_' . $item->getParent()->getName()] = $info;
    return $build;
  }

}
