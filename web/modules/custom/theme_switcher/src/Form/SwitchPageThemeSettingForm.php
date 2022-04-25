<?php

namespace Drupal\theme_switcher\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Render the config form of theme_switcher.
 */
class SwitchPageThemeSettingForm extends ConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler, LanguageManagerInterface $language_manager = NULL) {
    parent::__construct($config_factory);
    $this->moduleHandler = $module_handler;
    if ($language_manager) {
      $this->languageManager = $language_manager;
    }
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $languageServices = NULL;
    if ($container->has('language_manager')) {
      $languageServices = $container->get('language_manager');
    }
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('theme_handler'),
      $languageServices
    );
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['theme_switcher.settings'];
  }

  /**
   * Returns a unique string identifying the form.
   *
   * The returned ID should be a unique string that can be a valid PHP function
   * name, since it's used in hook implementation names such as
   * hook_form_FORM_ID_alter().
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'theme_switcher_admin_settings';
  }

  /**
   * {@inheritdoc}
   *
   * Implements admin settings form.
   *
   * @param array $form
   *   From render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current state of form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Fetch configurations if saved.
    $config = $this->config('theme_switcher.settings');

    // Instructions.
    $availableSettings = $this->t('Roles');
    if ($this->languageManager->isMultilingual() || $this->moduleHandler->moduleExists('language')) {
      $availableSettings .= '/' . $this->t('Languages');
    }
    $form['desc'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<b>Enable</b> Rule will work only if checkbox is checked.<br /><b>Pages:</b> Enter one path per line. The "*" character is a wildcard. Example paths are "/node/1" for an individual piece of content or "/node/*" for every piece of content. "@front" is the front page.<br /><b>@availableSettings:</b> Select none to allow all.<br /><br />Theme with highest weight will be applied on the page.', [
        '@availableSettings' => $availableSettings,
        '@front' => '<front>',
      ]),
    ];

    // Create headers for table.
    $header = [
      $this->t('Enabled'),
      $this->t('pages'),
      $this->t('Themes'),
      $this->t('Roles'),
    ];
    if ($this->languageManager->isMultilingual() || $this->moduleHandler->moduleExists('language')) {
      $header[] = $this->t('Language');
    }
    array_push($header, $this->t('Operation'), $this->t('Weight'));

    // Multi value table form.
    $form['spt_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('There are no items yet. Add an item.', []),
      '#prefix' => '<div id="spt-fieldset-wrapper">',
      '#suffix' => '</div>',
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'spt_table-order-weight',
        ],
      ],
    ];

    // Available themes.
    $themes = $this->themeHandler->listInfo();
    $themeNames[''] = '--Select--';
    foreach ($themes as $theme_name => $theme_info) {
      $themeNames[$theme_name] = $theme_info->info['name'];
    }

    // Set table values on Add/Remove or on page load.
    $spt_table = $form_state->get('spt_table');
    if (empty($spt_table)) {
      // Set data from configuration on page load.
      // Set empty element if no configurations are set.
      if (!empty($config->get('spt_table'))) {
        $spt_table = $config->get('spt_table');
        $form_state->set('spt_table', $spt_table);
      }
      else {
        $spt_table = [''];
        $form_state->set('spt_table', $spt_table);
      }
    }

    // Provide ability to remove first element.
    // Set Pages & Theme to required based on condition.
    $required = TRUE;
    if (isset($spt_table['removed']) && $spt_table['removed']) {
      // Not required if first element is empty.
      $first_element = reset($spt_table);
      $req_roles = FALSE;
      if ($first_element['pages'] == '' && $first_element['theme'] == '' && $first_element['status'] == '') {
        foreach ($first_element['roles'] as $value) {
          if ($value != 0) {
            $req_roles = TRUE;
          }
        }
        if (!$req_roles) {
          $required = FALSE;
        }
      }
      unset($spt_table['removed']);
    }
    // Don't allow to add multiple elements after all rows are removed.
    if (count($spt_table) > 1) {
      $required = TRUE;
    }

    // Create row for table.
    foreach ($spt_table as $i => $value) {
      $form['spt_table'][$i]['#attributes']['class'][] = 'draggable';
      $form['spt_table'][$i]['status'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Status'),
        '#title_display' => 'invisible',
        '#default_value' => isset($value['status']) ? $value['status'] : NULL,
      ];

      $form['spt_table'][$i]['pages'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Pages'),
        '#title_display' => 'invisible',
        '#required' => $required,
        '#cols' => '5',
        '#rows' => '5',
        '#default_value' => isset($value['pages']) ? $value['pages'] : [],
      ];

      $form['spt_table'][$i]['theme'] = [
        '#type' => 'select',
        '#title' => $this->t('Theme'),
        '#title_display' => 'invisible',
        '#options' => $themeNames,
        '#required' => $required,
        '#default_value' => isset($value['theme']) ? $value['theme'] : [],
      ];

      $form['spt_table'][$i]['roles'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Roles'),
        '#title_display' => 'invisible',
        '#options' => user_role_names(),
        '#default_value' => isset($value['roles']) ? $value['roles'] : [],
      ];

      // Add Language if site is multilingual.
      if ($this->languageManager->isMultilingual() || $this->moduleHandler->moduleExists('language')) {
        foreach ($this->languageManager->getLanguages() as $langkey => $langvalue) {
          $langNames[$langkey] = $langvalue->getName();
        }
        $form['spt_table'][$i]['language'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Language'),
          '#title_display' => 'invisible',
          '#options' => $langNames,
          '#default_value' => isset($value['language']) ? $value['language'] : [],
        ];
      }

      $form['spt_table'][$i]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => "remove-" . $i,
        '#submit' => ['::removeElement'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::removeCallback',
          'wrapper' => 'spt-fieldset-wrapper',
        ],
        '#index_position' => $i,
      ];

      // TableDrag: Weight column element.
      $form['spt_table'][$i]['weight'] = [
        '#type' => 'weight',
        '#title_display' => 'invisible',
        '#default_value' => isset($value['weight']) ? $value['weight'] : [],
        '#attributes' => ['class' => ['spt_table-order-weight']],
      ];
    }

    $form['add_name'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more'),
      '#submit' => ['::addOne'],
      '#ajax' => [
        'callback' => '::addmoreCallback',
        'wrapper' => 'spt-fieldset-wrapper',
      ],
    ];

    $form_state->setCached(FALSE);

    return parent::buildForm($form, $form_state);
  }

  /**
   * Callback for ajax-enabled add buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function addmoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['spt_table'];
  }

  /**
   * Submit handler for the "Add one more" button.
   *
   * Add a blank element in table and causes a rebuild.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $spt_table = $form_state->get('spt_table');
    array_push($spt_table, "");
    $form_state->set('spt_table', $spt_table);
    $form_state->setRebuild();
  }

  /**
   * Callback for ajax-enabled remove buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    return $form['spt_table'];
  }

  /**
   * Submit handler for the "Remove" button(s).
   *
   * Remove the element from table and causes a form rebuild.
   */
  public function removeElement(array &$form, FormStateInterface $form_state) {
    // Get table.
    $spt_table = $form_state->get('spt_table');
    // Get element to remove.
    $remove = key($form_state->getValue('spt_table'));
    // Remove element.
    unset($spt_table[$remove]);
    // Set an empty element if no elements are left.
    if (empty($spt_table)) {
      array_push($spt_table, "");
    }
    // Set removed flag for removed item.
    $spt_table['removed'] = TRUE;
    $form_state->set('spt_table', $spt_table);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->config('theme_switcher.settings')
      // Set the submitted configuration setting.
      ->set('spt_table', $form_state->getValue('spt_table'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
