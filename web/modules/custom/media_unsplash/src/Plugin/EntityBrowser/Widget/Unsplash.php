<?php

namespace Drupal\media_unsplash\Plugin\EntityBrowser\Widget;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\BeforeCommand;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\MimeType\ExtensionMimeTypeGuesser;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\entity_browser\WidgetBase;
use Drupal\entity_browser\WidgetValidationManager;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Uses a view to provide entity listing in a browser's widget.
 *
 * @EntityBrowserWidget(
 *   id = "unsplash",
 *   label = @Translation("Unsplash"),
 *   description = @Translation("Adds a Unsplash field browser's widget."),
 *   auto_select = FALSE
 * )
 */
class Unsplash extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * Renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   *   Renderer.
   */
  protected $renderer;

  /**
   * Cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   *   Cache.
   */
  protected $cache;

  /**
   * The media Unsplash access key config.
   *
   * @var \Drupal\Core\Config\Config
   *   Config factory
   */
  protected $unsplashAccessKeyConfig;

  /**
   * The media Unsplash secret key config.
   *
   * @var \Drupal\Core\Config\Config
   *   Config factory
   */
  protected $unsplashSecretKeyConfig;

  /**
   * Error logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The "file_system" service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Upload constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\entity_browser\WidgetValidationManager $validation_manager
   *   The Widget Validation Manager service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   Renderer.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache.
   * @param \Drupal\Core\Config\Config $config_factory
   *   Unsplash API key config.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Error logger.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   Current user.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   File system.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   Http client.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EventDispatcherInterface $event_dispatcher,
    EntityTypeManagerInterface $entity_type_manager,
    WidgetValidationManager $validation_manager,
    Renderer $renderer,
    CacheBackendInterface $cache,
    Config $config_factory,
    LoggerChannelFactoryInterface $logger,
    AccountProxy $current_user,
    FileSystemInterface $file_system,
    ClientInterface $http_client,
    ModuleHandlerInterface $module_handler,
    Token $token,
    MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $event_dispatcher, $entity_type_manager, $validation_manager);
    $this->renderer = $renderer;
    $this->cache = $cache;
    $this->unsplashAccessKeyConfig = $config_factory->get('media_unsplash_access_key');
    $this->unsplashSecretKeyConfig = $config_factory->get('media_unsplash_secret_key');
    $this->unsplashAppNameConfig = $config_factory->get('media_unsplash_app_name');
    $this->loggerFactory = $logger;
    $this->currentUser = $current_user;
    $this->fileSystem = $file_system;
    $this->httpClient = $http_client;
    $this->moduleHandler = $module_handler;
    $this->token = $token;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.entity_browser.widget_validation'),
      $container->get('renderer'),
      $container->get('cache.default'),
      $container->get('config.factory')->get('media_unsplash.admin.config'),
      $container->get('logger.factory'),
      $container->get('current_user'),
      $container->get('file_system'),
      $container->get('http_client'),
      $container->get('module_handler'),
      $container->get('token'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'upload_location' => 'public://unsplash/[UNSPLASH_SEARCH_TERM]/',
      'multiple' => TRUE,
      'submit_text' => $this->t('Select images'),
      'search_text' => $this->t('Search Unsplash'),
      'search_placeholder' => $this->t('Enter search terms...'),
      'extensions' => '',
      'media_type' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['search_placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search bar text'),
      '#default_value' => $this->configuration['search_placeholder'],
    ];

    $form['search_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search button text'),
      '#default_value' => $this->configuration['search_text'],
    ];

    $form['multiple'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Accept multiple files'),
      '#default_value' => $this->configuration['multiple'],
      '#description' => $this->t('Multiple uploads will only be accepted if the source field allows more than one value.'),
    ];

    $form['upload_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Upload location'),
      '#default_value' => $this->configuration['upload_location'],
      '#description' => $this->t('[UNSPLASH_SEARCH_TERM] token will be replaced with the search term.'),
    ];

    $form['multiple'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Accept multiple files'),
      '#default_value' => $this->configuration['multiple'],
      '#description' => $this->t('Multiple uploads will only be accepted if the source field allows more than one value.'),
    ];

    $form['extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed extensions'),
      '#description' => $this->t('The allowed extensions are loaded from the selected media type.'),
      '#default_value' => $this->configuration['extensions'],
      '#element_validate' => [[FileItem::class, 'validateExtensions']],
      '#prefix' => '<div id="unsplash-allowed-extensions">',
      '#suffix' => '</div>',
    ];

    $form['identifier'] = [
      '#type' => 'hidden',
      '#value' => 'unsplash-widget',
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      $form['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['file'],
      ];
      $form['upload_location']['#description'] = $this->t('You can use tokens in the upload location ([UNSPLASH_SEARCH_TERM] token will be replaced with the search term).');
    }

    try {
      $mediaTypeOptions = [];
      $mediaTypes = $this
        ->entityTypeManager
        ->getStorage('media_type')
        ->loadByProperties(['source' => 'image']);
      foreach ($mediaTypes as $mediaType) {
        $mediaTypeOptions[$mediaType->id()] = $mediaType->label();
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('media_unsplash')
        ->alert($e->getMessage());
    }

    if (empty($mediaTypeOptions)) {
      $url = Url::fromRoute('entity.media_type.add_form')->toString();
      $form['media_type'] = [
        '#markup' => $this->t("You don't have a media type of the Image type. You should <a href='!link'>create one</a>", ['!link' => $url]),
      ];
    }
    else {
      $form['media_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Media type'),
        '#default_value' => $this->configuration['media_type'],
        '#options' => $mediaTypeOptions,
        '#required' => TRUE,
        '#ajax' => [
          'callback' => [$this, 'extensionsAjaxCallback'],
          'disable-refocus' => FALSE,
          'wrapper' => 'unsplash-allowed-extensions',
          'event' => 'change',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Verifying entry...'),
          ],
        ],
      ];
    }

    return $form;
  }

  /**
   * Adjusts allowed extensions for the selected media type.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Form array with changed data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function extensionsAjaxCallback(array &$form, FormStateInterface $form_state) {
    $mediaType = $form_state->getValue('table')[$this->uuid()]['form']['media_type'];
    /** @var \Drupal\media\Entity\MediaType $mediaTypeLoaded */
    $mediaTypeLoaded = $this->entityTypeManager
      ->getStorage('media_type')
      ->load($mediaType);
    if (empty($mediaTypeLoaded)) {
      $uuid = $this->uuid();
      return $form['widgets']['table'][$uuid]['form']['extensions'];
    }
    /** @var \Drupal\field\Entity\FieldConfig $fieldInstance */
    $fieldInstance = $this->entityTypeManager->getStorage('field_config')
      ->load('media.' . $mediaType . '.' . $mediaTypeLoaded->getSource()->getConfiguration()['source_field']);
    $extensions = $fieldInstance->get('settings')['file_extensions'];
    $this->configuration['extensions'] = $extensions;
    $this->configuration['media_type'] = $mediaType;
    $uuid = '';
    if (empty($this->uuid())) {
      foreach ($form_state->getValue('table') as $key => $value) {
        if (is_array($value) && isset($value['form']['identifier']) && $value['form']['identifier'] == 'unsplash-widget') {
          $uuid = $key;
        }
      }
    }
    else {
      $uuid = $this->uuid();
    }
    $form['widgets']['table'][$uuid]['form']['extensions']['#value'] = $extensions;
    return $form['widgets']['table'][$uuid]['form']['extensions'];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form['#attached']['library'][] = 'core/drupal.ajax';
    $form['#attached']['library'][] = 'media_unsplash/media-unsplash-js-library';
    $form['#attached']['library'][] = 'media_unsplash/media-unsplash-css-library';

    try {
      /** @var \Drupal\media\MediaTypeInterface $mediaType */
      $mediaType = $this->entityTypeManager->getStorage('media_type')
        ->load($this->configuration['media_type']);
      if (!$this->configuration['media_type'] || empty($mediaType)) {
        return ['#markup' => $this->t('The media type is not configured correctly.')];
      }
      if ($mediaType->getSource()->getPluginId() != 'image') {
        return ['#markup' => $this->t('The configured media type is not using the image plugin.')];
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('media_unsplash')
        ->alert($e->getMessage());
      unset($form['wrapper']['actions-bottom']);
    }

    $form['wrapper'] = [
      '#type' => 'container',
      '#prefix' => '',
      '#attributes' => ['id' => 'unsplash-wrapper'],
    ];

    /** @var \Drupal\media\Entity\MediaType $mediaType */
    $mediaType = $this->entityTypeManager
      ->getStorage('media_type')
      ->load($this->configuration['media_type']);
    if ($mediaType != NULL) {
      /** @var \Drupal\field\Entity\FieldConfig $fieldInstance */
      $fieldInstance = $this->entityTypeManager->getStorage('field_config')
        ->load('media.' . $this->configuration['media_type'] . '.' . $mediaType->getSource()->getConfiguration()['source_field']);
      $allowedExtensionsMt = explode(' ', $fieldInstance->get('settings')['file_extensions']);
      $allowedExtensionsWidget = explode(' ', $this->getConfiguration()['settings']['extensions']);
      if ($allowedExtensionsMt != $allowedExtensionsWidget && array_diff($allowedExtensionsWidget, $allowedExtensionsMt)) {
        $this->messenger->addMessage($this->t('The allowed extensions in the selected media type and in the Unsplash widget do not match. Please update the widget.'), 'error');
      }
    }

    $form['wrapper']['search_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Unsplash'),
      '#attributes' => [
        'placeholder' => $this->configuration['search_placeholder'],
        'class' => ['search-bar'],
      ],
    ];

    $form['wrapper']['actions-top'] = [
      [
        '#type' => 'actions',
        'search' => [
          '#type' => 'button',
          '#name' => 'unsplashSearch',
          '#value' => $this->configuration['search_text'],
          '#button_type' => 'primary',
        ],
      ],
    ];

    $form['wrapper']['pictures'] = [
      '#type' => 'container',
      '#prefix' => '<div id="result-images">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
      '#weight' => 10,
    ];

    $triggeringElement = $form_state->getTriggeringElement()['#array_parents'] ?? FALSE;
    if ($form_state->get('images') === NULL || in_array('widget_selector', $triggeringElement, TRUE)) {
      $form_state->set('images', []);
    }
    elseif ($results = $form_state->get('images')->results) {
      $max_pages = $form_state->get('images')->total_pages;
      $page = $form_state->getValue('page') ?? 1;
      $this->buildImages($results, $form);
      $form['wrapper']['page'] = [
        '#type' => 'hidden',
        '#value' => $page,
      ];

      $form['wrapper']['pictures']['pager'] = [
        '#type' => 'container',
        '#weight' => 1000,
        'pager' => [
          '#type' => 'actions',
          'load_more' => [
            '#type' => 'button',
            '#name' => 'unsplashLoadMore',
            '#ajax' => [
              'callback' => 'Drupal\media_unsplash\Plugin\EntityBrowser\Widget\Unsplash::unsplashLoadMore',
            ],
            '#access' => ($page < $max_pages) ? TRUE : FALSE,
            '#value' => $this->t('Load more images'),
          ],
        ],
      ];
    }

    $form['wrapper']['actions-bottom'] = [
      [
        '#type' => 'actions',
        '#attributes' => [
          'class' => ['actions-bottom'],
        ],
        'submit' => [
          '#type' => 'submit',
          '#name' => 'unsplashSubmit',
          '#value' => $this->configuration['submit_text'],
          '#eb_widget_main_submit' => TRUE,
          '#attributes' => [
            'class' => ['is-entity-browser-submit'],
          ],
          '#button_type' => 'primary',
        ],
      ],
    ];

    return $form;
  }

  /**
   * Sets the #checked property when rebuilding form.
   *
   * Every time when we rebuild we want all checkboxes to be unchecked.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   Returns the processed elements array.
   *
   * @see \Drupal\Core\Render\Element\Checkbox::processCheckbox()
   */
  public static function processCheckbox(array &$element, FormStateInterface $form_state, array &$complete_form) {
    if ($form_state->isRebuilding()) {
      $element['#checked'] = FALSE;
    }
    else {
      $value = $element['#value'];
      $returnValue = $element['#return_value'];
      if ($value === TRUE || $value === FALSE || $value === 0) {
        $element['#checked'] = (bool) $value;
      }
      else {
        $element['#checked'] = ($value['id'] === $returnValue['id']);
      }
    }
    return $element;
  }

  /**
   * Prepares entities to be selected by the entity browser.
   *
   * Creates files from images and saves them to the Unsplash directory based on
   * date of search (folders by months per year) and search term. Then media
   * entities are created and saved.
   *
   * @param array $form
   *   Complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of saved media entities.
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    $media = [];
    foreach ($form_state->getValue('pictures') as $key => $item) {
      if (!empty($item['checked'])) {
        $photo_url = $this->getPhotoUrl($item['checked']['download']);
        if (!$photo_url) {
          continue;
        }
        $searchTerm = $form_state->getValue('search_key');
        $title = $item['checked']['description'];
        $path = str_replace('[UNSPLASH_SEARCH_TERM]', $searchTerm, $this->token->replace($this->configuration['upload_location']));
        $path = rtrim($path, '/');
        if ($this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY)) {
          /** @var \Drupal\file\FileInterface $file */
          $file = system_retrieve_file($photo_url, $path, TRUE);
          if ($file !== FALSE) {
            // We need to append the file extension on the file because
            // some unsplash images do not use it in the URL
            // and Drupal doesn't know the file type without the extension.
            $fileUri = $file->getFileUri();
            $fileExtension = '.' . $item['checked']['file_extension'];
            if (substr($fileUri, -strlen($fileExtension)) != $fileExtension) {
              $newUri = $fileUri . $fileExtension;
              $file = file_move($file, $newUri);
              // Time to guess this type!
              $guesser = new ExtensionMimeTypeGuesser($this->moduleHandler);
              $mimeType = $guesser->guess($newUri);
              $file->setMimeType($mimeType);
              $file->save();
            }
            try {
              /** @var \Drupal\media\MediaTypeInterface $mediaType */
              $mediaType = $this->entityTypeManager
                ->getStorage('media_type')
                ->load($this->configuration['media_type']);
              if (empty($mediaType)) {
                continue;
              }
              // If name is empty use search term.
              if (empty($title)) {
                $title = $searchTerm;
              }
              $name = Unicode::truncate('Unsplash: ' . $title, 255, TRUE, FALSE);
              /** @var \Drupal\media\Entity\Media[] $media */
              $media[$key] = $this->entityTypeManager->getStorage('media')
                ->create([
                  'bundle' => $this->configuration['media_type'],
                  'uid' => $this->currentUser->id(),
                  'status' => TRUE,
                  $mediaType->getSource()->getConfiguration()['source_field'] => [
                    'target_id' => $file->id(),
                    'title' => 'Unsplash: ' . $title,
                    'alt' => $item['checked']['tags'],
                  ],
                ]);
              $media[$key]->setName($name)
                ->save();
              $media = array_values($media);
            }
            catch (\Exception $e) {
              $this->loggerFactory->get('media_unsplash')
                ->alert($e->getMessage());
            }
          }
        }
      }
    }
    return $media;
  }

  /**
   * This gets the url of the photo from the download link.
   *
   * @param string $downloadLink
   *   Link to which to get the photo URL.
   *
   * @returns string
   *   URL of the photo
   */
  private function getPhotoUrl(string $downloadLink) {
    try {
      $result = $this->httpClient->request('GET', $downloadLink, [
        'headers' => [
          'Accept' => 'application/json',
          'Content-type' => 'application/json',
          'Accept-Version' => 'v1',
          'Authorization' => 'Client-ID ' . $this->unsplashAccessKeyConfig,

        ],
      ]);
      if ($result) {
        $unsplashReturn = \GuzzleHttp\json_decode($result->getBody()->getContents());
        return $unsplashReturn->url;
      }
      return NULL;
    }
    catch (GuzzleException $e) {
      $this->messenger()->addMessage($this->t('The download has failed for "@url" failed with error "@error" (HTTP code @code).', [
        '@url' => UrlHelper::filterBadProtocol($downloadLink),
        '@error' => $e->getMessage(),
        '@code' => $e->getCode(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function validate(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] === 'unsplashSubmit') {
      $mediaType = $this->configuration['media_type'];
      /** @var \Drupal\media\MediaTypeInterface $mediaTypeLoaded */
      $mediaTypeLoaded = $this->entityTypeManager
        ->getStorage('media_type')
        ->load($mediaType);
      if (empty($mediaTypeLoaded)) {
        $form_state->setError($this->t('The media does not exist.'));
        return;
      }
      $sourceField = $mediaTypeLoaded->getSource()->getConfiguration()['source_field'];
      /** @var \Drupal\field\Entity\FieldConfig $mediaTypeFieldConfig */
      $mediaTypeFieldConfig = $this->entityTypeManager
        ->getStorage('field_config')->load('media.' . $mediaType . '.' . $sourceField);
      $mediaTypeExtensions = $mediaTypeFieldConfig->getSetting('file_extensions');
      foreach ($form_state->getValue('pictures') as $key => $item) {
        if (!empty($item['checked'])) {
          array_push($form['widget']['wrapper']['pictures'][$key]['#attributes']['class'], 'unsplash-selected-image');
          $fileExtension = $item['checked']['file_extension'] ?? pathinfo($item['checked']['download'], PATHINFO_EXTENSION);
          if (strpos($this->configuration['extensions'], $fileExtension) === FALSE) {
            $form_state->setError($form['widget']['wrapper']['pictures'][$key],
              $this->t("Extension @extension doesn't match the allowed extensions set in the widget configuration.", ['@extension' => $fileExtension]));
            array_push($form['widget']['wrapper']['pictures'][$key]['#attributes']['class'], 'unsplash-configuration-error');
          }
          elseif (strpos($mediaTypeExtensions, $fileExtension) === FALSE) {
            $form_state->setError($form['widget']['wrapper']['pictures'][$key],
              $this->t("Extension @extension doesn't match the allowed extensions set in the media type.", ['@extension' => $fileExtension]));
            array_push($form['widget']['wrapper']['pictures'][$key]['#attributes']['class'], 'unsplash-configuration-error');
          }
        }
      }
    }
    if ($form_state->getTriggeringElement()['#name'] === 'unsplashLoadMore') {
      $page = $form_state->getValue('page') ?? 1;
      $form_state->setValue('page', $page + 1);
    }
    if ($form_state->getTriggeringElement()['#name'] === 'unsplashSearch') {
      // Reset pager on new search.
      $form_state->setValue('page', 1);
    }
    if ($form_state->getTriggeringElement()['#name'] === 'unsplashSearch'
        || $form_state->getTriggeringElement()['#name'] === 'unsplashLoadMore'
      ) {
      if (empty($this->unsplashAccessKeyConfig)) {
        $form_state->setError($form['widget']['wrapper']['search_key'], $this->t('Please configure your Unsplash API keys.'));
        return;
      }
      $searchTerm = $form_state->getValue('search_key');
      $page = $form_state->getValue('page') ?? 1;
      if (empty($searchTerm)) {
        unset($form['widget']['wrapper']['actions-bottom']);
        $form['widget']['wrapper']['pictures'] = NULL;
        $form_state->setError($form['widget']['wrapper']['search_key'], $this->t('Please enter a search term.'));
      }
      else {
        try {
          if ($page === 1) {
            // Reset to only first page.
            $images = $this->fetchImages($searchTerm);
            if (!empty($images)) {
              $form_state->set('images', $images);
            }
            else {
              unset($form['widget']['wrapper']['actions-bottom']);
              unset($form['widget']['wrapper']['pager']);
              $form['widget']['wrapper']['pictures'] = NULL;
              $form_state->setError($form['widget']['wrapper']['search_key'],
              $this->t('Current search provided no results, please enter a valid search term.'));
            }
          }
          else {
            $unsplashResults = $form_state->get('images');
            $images = $this->fetchImages($searchTerm, $page);
            if (!empty($images)) {
              $unsplashResults->results = array_merge($unsplashResults->results, $images->results);
              $form_state->set('images', $unsplashResults);
            }
          }

        }
        catch (GuzzleException $e) {
          $form_state->setError($form['widget']['wrapper']['search_key'],
            $this->t('The validation of "@url" failed with error "@error" (HTTP code @code).', [
              '@url' => UrlHelper::filterBadProtocol('https://api.unsplash.com/search/photos/?' . $queryStr),
              '@error' => $e->getMessage(),
              '@code' => $e->getCode(),
            ]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $images = $this->prepareEntities($form, $form_state);
      $this->selectEntities($images, $form_state);
    }
  }

  /**
   * Builds images.
   *
   * @param array $content
   *   Resulting images.
   * @param array $form
   *   Form.
   */
  private function buildImages(array $content, array &$form) {
    foreach ($content as $key => $unsplashImage) {
      $form['wrapper']['pictures'][$key] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['unsplash-image-wrapper'],
        ],
        '#tree' => TRUE,
      ];

      $tags = implode(',', array_map(function ($tag) {
          return $tag->title;
      }, $unsplashImage->tags));
      $query_parameters = [];
      parse_str(parse_url($content[0]->urls->full)['query'], $query_parameters);
      $fileExtension = $query_parameters['fm'] ? $query_parameters['fm'] : 'jpg';
      $form['wrapper']['pictures'][$key]['checked'] = [
        '#type' => 'checkbox',
        '#attributes' => [
          'class' => ['unsplash-image-check'],
        ],
        '#process' => [
          [
            '\Drupal\media_unsplash\Plugin\EntityBrowser\Widget\Unsplash',
            'processCheckbox',
          ],
          ['\Drupal\Core\Render\Element\Checkbox', 'processAjaxForm'],
        ],
        '#return_value' => [
          'description' => $unsplashImage->description,
          'download' => $unsplashImage->links->download_location,
          'file_extension' => $fileExtension,
          'preview' => $unsplashImage->urls->small,
          'id' => $unsplashImage->id,
          'tags' => $tags,
        ],
      ];

      /*
       * When displaying a photo from Unsplash, your application must attribute
       * Unsplash, the Unsplash photographer, and contain a link back to their
       * Unsplash profile. All links back to Unsplash should use utm parameters
       * in the ?utm_source=your_app_name&utm_medium=referral
       */
      $form['wrapper']['pictures'][$key]['image'] = [
        '#markup' => '<img src="' . $unsplashImage->urls->thumb . '"
            alt="Unsplash image ' . $unsplashImage->alt_description . '" class="unsplash-image" />',
      ];
      $appName = $this->unsplashAppNameConfig;
      $form['wrapper']['pictures'][$key]['photographer'] = [
        '#markup' => '<div class="subtitle">' .
        $this->t('Photo by') . ' <a href="' . $unsplashImage->user->links->html .
        '?utm_source=' . $appName . '&utm_medium=referral" target="_blank">' . $unsplashImage->user->name . '</a><br>
        on <a href="https://unsplash.com/?utm_source=' . $appName . '&utm_medium=referral" target="_blank">Unsplash</a>
         </div>',
      ];

    }
  }

  /**
   * Load images from Unsplash.
   */
  public function fetchImages($searchTerm, $page = 1) {
    if (!$searchTerm) {
      return [];
    }
    $unsplashReturn = [];
    $context = [
      'query' => $searchTerm,
      'per_page' => 25,
      'page' => $page,
    ];
    $queryStr = UrlHelper::buildQuery($context);
    // Check if we have cached results for current search term.
    $cid = 'media_unsplash:' . $searchTerm . ':' . $page;
    if ($cache = $this->cache->get($cid)) {
      $unsplashReturn = $cache->data;
    }
    else {
      // Load images from unsplash.
      $result = $this->httpClient->request('GET', 'https://api.unsplash.com/search/photos/?' . $queryStr, [
        'headers' => [
          'Accept' => 'application/json',
          'Content-type' => 'application/json',
          'Accept-Version' => 'v1',
          'Authorization' => 'Client-ID ' . $this->unsplashAccessKeyConfig,

        ],
      ]);
      $unsplashReturn = \GuzzleHttp\json_decode($result->getBody()
        ->getContents());
      if ($unsplashReturn->results) {
        // Set the request cache to 24 hours, according to Unsplash API
        // rules.
        $this->cache->set($cid, $unsplashReturn, time() + 24 * 60 * 60, ['unsplash:' . $searchTerm . ':' . $page]);
      }
      else {
        return [];
      }
    }
    return $unsplashReturn;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $mediaType = $form_state->getValue('table')[$this->uuid()]['form']['media_type'];
    /** @var \Drupal\media\Entity\MediaType $mediaTypeLoaded */
    $mediaTypeLoaded = $this->entityTypeManager
      ->getStorage('media_type')
      ->load($mediaType);
    if ($mediaType != NULL) {
      /** @var \Drupal\field\Entity\FieldConfig $fieldInstance */
      $fieldInstance = $this->entityTypeManager->getStorage('field_config')
        ->load('media.' . $mediaType . '.' . $mediaTypeLoaded->getSource()->getConfiguration()['source_field']);
      $allowedExtensions = explode(" ", $fieldInstance->get('settings')['file_extensions']);
      $submittedExtensionsArray = explode(" ", $form_state->getValue('table')[$this->uuid()]['form']['extensions']);
      if ($allowedExtensions != $submittedExtensionsArray && array_diff($submittedExtensionsArray, $allowedExtensions)) {
        $form_state->setErrorByName('table][' . $this->uuid() . '][form][extensions',
          $this->t('The allowed extensions on this widget do not match those that are allowed on the media type @media.', [
            '@media' => $mediaType,
          ]));
      }
    }
  }

  /**
   * Ajax callback to load more images.
   */
  public static function unsplashLoadMore(array $form, FormStateInterface $form_state) {

    $images_element = $form['widget']['wrapper']['pictures'];
    $images = [];
    foreach ($images_element as $image) {
      if (is_array($image) && isset($image['image'])) {
        $images[] = $image;
      }

    }
    // We only need to supply the new images.
    $page = $form_state->getValue('page') ?? 1;
    $offset = ($page - 1) * 25;

    $response = new AjaxResponse();
    $response->addCommand(
      new BeforeCommand(
        '#edit-pictures-pager',
        array_slice($images, $offset, 25, TRUE),
        ),
    );
    return $response;

  }

}
