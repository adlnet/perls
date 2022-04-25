<?php

namespace Drupal\perls_authoring\Plugin\EntityBrowser\Widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\entity_browser\Plugin\EntityBrowser\Widget\View;

/**
 * Uses a view to provide entity listing in a browser's widget.
 *
 * Views using this widget are passed an argument containing current selection.
 *
 * @EntityBrowserWidget(
 *   id = "view_exclude_selection",
 *   label = @Translation("View exclude current selection"),
 *   provider = "views",
 *   description = @Translation("Uses a view to provide entity listing in a browser's widget, this view is passed current selection."),
 *   auto_select = TRUE
 * )
 */
class ViewExcludeSelection extends View {

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form = [];

    if ($form_state->has(['entity_browser', 'widget_context'])) {
      $this->handleWidgetContext($form_state->get([
        'entity_browser',
        'widget_context',
      ]));
    }

    // Check if widget supports auto select functionality and expose config to
    // front-end javascript.
    $autoSelect = FALSE;
    if ($this->getPluginDefinition()['auto_select']) {
      $autoSelect = $this->configuration['auto_select'];
      $form['#attached']['drupalSettings']['entity_browser_widget']['auto_select'] = $autoSelect;
    }

    // In case of auto select, widget will handle adding entities in JS.
    if (!$autoSelect) {
      $form['actions'] = [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->configuration['submit_text'],
          '#eb_widget_main_submit' => TRUE,
          '#attributes' => ['class' => ['is-entity-browser-submit']],
          '#button_type' => 'primary',
        ],
      ];
    }
    // @todo do we need better error handling for view and view_display (in
    // case either of those is nonexistent or display not of correct type)?
    $form['#attached']['library'] = ['entity_browser/view'];

    /** @var \Drupal\views\ViewExecutable $view */
    $view = $this->entityTypeManager
      ->getStorage('view')
      ->load($this->configuration['view'])
      ->getExecutable();
    $context = $form_state->get(['entity_browser', 'widget_context']);
    if (!empty($context['current_ids'])) {
      $view->setArguments([$context['current_ids']]);
    }

    $form['view'] = $view->executeDisplay($this->configuration['view_display']);

    if (empty($view->field['entity_browser_select'])) {
      $url = Url::fromRoute('entity.view.edit_form', ['view' => $this->configuration['view']])->toString();
      if ($this->currentUser->hasPermission('administer views')) {
        return [
          '#markup' => $this->t('Entity browser select form field not found on a view. <a href=":link">Go fix it</a>!', [':link' => $url]),
        ];
      }
      else {
        return [
          '#markup' => $this->t('Entity browser select form field not found on a view. Go fix it!'),
        ];
      }
    }

    // When rebuilding makes no sense to keep checkboxes that were previously
    // selected.
    if (!empty($form['view']['entity_browser_select'])) {
      foreach (Element::children($form['view']['entity_browser_select']) as $child) {
        // @codingStandardsIgnoreStart
        $form['view']['entity_browser_select'][$child]['#process'][] = ['\Drupal\entity_browser\Plugin\EntityBrowser\Widget\View', 'processCheckbox'];
        $form['view']['entity_browser_select'][$child]['#process'][] = ['\Drupal\Core\Render\Element\Checkbox', 'processAjaxForm'];
        $form['view']['entity_browser_select'][$child]['#process'][] = ['\Drupal\Core\Render\Element\Checkbox', 'processGroup'];
        // @codingStandardsIgnoreEnd
      }
    }

    $form['view']['view'] = [
      '#markup' => \Drupal::service('renderer')->render($form['view']['view']),
    ];

    return $form;
  }

}
