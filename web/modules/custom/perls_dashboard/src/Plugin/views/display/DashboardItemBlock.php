<?php

namespace Drupal\perls_dashboard\Plugin\views\display;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\Block\ViewsBlock;
use Drupal\views\Plugin\views\display\Block;

/**
 * The plugin that handles a dashboard item block.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "dashboard_block",
 *   title = @Translation("Dashboard block"),
 *   help = @Translation("Display the view as a block."),
 *   theme = "views_view",
 *   register_theme = FALSE,
 *   uses_hook_block = TRUE,
 *   contextual_links_locations = {"block"},
 *   admin = @Translation("Dashboard block")
 * )
 *
 * @see \Drupal\views\Plugin\Block\ViewsBlock
 * @see \Drupal\views\Plugin\Derivative\ViewsBlock
 */
class DashboardItemBlock extends Block {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['more_content_path'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);
    $more_path = $this->getOption('more_content_path');
    if (empty($more_path)) {
      $more_path = $this->t('None');
    }
    $options['more_content_path'] = [
      'category' => 'block',
      'title' => $this->t('More content path'),
      'value' => $more_path,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    if ($form_state->get('section') === 'more_content_path') {
      $form['more_content_path'] = [
        '#type' => 'textfield',
        '#title' => $this->t('More content path'),
        '#default_value' => $this->getOption('more_content_path'),
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    $section = $form_state->get('section');
    if ($section === 'more_content_path') {
      $this->setOption($section, $form_state->getValue($section));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm(ViewsBlock $block, array &$form, FormStateInterface $form_state) {
    $form = parent::blockForm($block, $form, $form_state);
    $block_configuration = $block->getConfiguration();
    $form['override']['more_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('More content URL'),
      '#description' => $this->t('Relative path please start the url with slash, like /path'),
      '#default_value' => empty($block_configuration['more_url']) ? $this->view->display_handler->getOption('more_content_path') : $block_configuration['more_url'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit(ViewsBlock $block, $form, FormStateInterface $form_state) {
    parent::blockSubmit($block, $form, $form_state);
    if ($more_url = $form_state->getValue(['override', 'more_url'])) {
      $block->setConfigurationValue('more_url', $more_url);
    }
  }

}
