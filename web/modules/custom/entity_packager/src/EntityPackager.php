<?php

namespace Drupal\entity_packager;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Random;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\State;
use Drupal\file\Entity\File;
use Drupal\entity_packager\Event\EntityPrePackageEvent;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Save the full view of an entity into a zip file.
 */
class EntityPackager {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $currentRequest;

  /**
   * The field where the wget will save the cookie for further usage.
   *
   * @var string
   */
  protected $cookieFilePath = '';

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $moduleLogger;

  /**
   * Offline page helper class.
   *
   * @var \Drupal\entity_packager\NodePackagerStorageHelper
   */
  protected $packageStorageHelper;

  /**
   * Drupal state api.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * A cache tag invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheInvalidator;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * User Agent used in wget.
   *
   * @var string
   */
  private $userAgent = '';

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Create an offline version of entity.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   File sysyem service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Logger service.
   * @param \Drupal\entity_packager\NodePackagerStorageHelper $offline_helper
   *   The node packager helper class.
   * @param \Drupal\Core\State\State $state
   *   Drupal state api.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tag_invalidator
   *   Drupal cache tag invalidator service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispactcher
   *   Event dispatcher service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system,
    RequestStack $request_stack,
    LoggerChannelFactoryInterface $logger,
    NodePackagerStorageHelper $offline_helper,
    State $state,
    CacheTagsInvalidatorInterface $cache_tag_invalidator,
    ConfigFactoryInterface $config_factory,
    EventDispatcherInterface $event_dispactcher
    ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->moduleLogger = $logger->get('entity_packager');
    $this->packageStorageHelper = $offline_helper;
    $this->cookieFilePath = $this->generateCookieFilePath();
    $this->state = $state;
    $this->cacheInvalidator = $cache_tag_invalidator;
    $this->configFactory = $config_factory;
    $this->eventDispatcher = $event_dispactcher;
    $this->setUserAgent();
  }

  /**
   * Class destructor.
   */
  public function __destruct() {
    if (file_exists($this->cookieFilePath)) {
      $this->fileSystem->delete($this->cookieFilePath);
    }
    drupal_static_reset('OFFLINE_PAGE_NEEDS_RUN');
    drupal_static_reset('OFFLINE_PAGE_PREVIOUS_RESULT');
  }

  /**
   * Generate a random file url for cookie.txt.
   *
   * @return string
   *   The uri of cookie.txt.
   */
  private function generateCookieFilePath() {
    return $this->fileSystem->getTempDirectory() . '/cookie' . (new Random)->name() . '.txt';
  }

  /**
   * Set user agent.
   *
   * @param string $user_agent
   *   Optional User Agent to attempt to set.
   */
  protected function setUserAgent($user_agent = '') {
    if (!$user_agent) {
      $user_agent = $this->configFactory->get('entity_packager.page_settings')
        ->get('user_agent');
    }

    if ($error_message = $this->isUserAgentUnsafe($user_agent)) {
      $this->moduleLogger->warning("User Agent is invalid and has not been set. " . $error_message);
      return;
    }

    $this->userAgent = $user_agent;

  }

  /**
   * Get Safe User Agent.
   *
   * @param string $user_agent
   *   Optional User Agent to attempt set.
   *
   * @return null|string
   *   User agent filtered by preg expression.
   */
  public function isUserAgentUnsafe($user_agent) {
    $safe_user_agent = preg_replace("/[^A-Za-z0-9 ]/", '', $user_agent);
    if ($user_agent === $safe_user_agent) {
      return NULL;
    }
    return t('The User Agent must contain only letters and numbers.');
  }

  /**
   * Save the entity's full view mode into html file then into zip it.
   *
   * @param string $entity_type
   *   The entity type which id belongs to.
   * @param int $entity_id
   *   The entity id.
   *
   * @return bool
   *   Indicated that the process was successful or not.
   *   Returns FALSE if the attempt failed and needs to be tried again.
   *   Returns TRUE if the process does not need to be attempted again.
   */
  public function generateZip($entity_type, $entity_id) {
    if (empty($this->state->get('wget_user'))) {
      $this->moduleLogger->error("The wget user isn't configured, please set it.");
      return FALSE;
    }
    // Check to see if wget_user has correct role.
    $user = User::load($this->state->get('wget_user'));
    if (!$user->hasRole('packager')) {
      $this->moduleLogger->error("The wget user does not have the 'packager' Role.");
      return FALSE;
    }
    try {
      $entity = $this->entityTypeManager->getStorage($entity_type)
        ->load($entity_id);

      if (empty($entity)) {
        $this->moduleLogger->error('The @entity_type type with @entity_id entity id does not exist.', [
          '@entity_type' => $entity_type,
          '@entity_id' => $entity_id,
        ]);
        // Return TRUE here so we do not throw an error in the queue worker.
        // This will prevent missing nodes from being re-processed
        // over and over.
        return TRUE;
      }

      $event = new EntityPrePackageEvent($entity);
      $this->eventDispatcher->dispatch(EntityPrePackageEvent::EVENT_ID, $event);
      // We don't continue the packaging process because one of event subscriber
      // doesn't want.
      if (!$event->isNeedPackaging()) {
        $this->deletePackage($entity);
        $this->moduleLogger->notice('One of modules has prevented packaging of @entity entity.', [
          '@entity' => $entity_id,
        ]);
        return FALSE;
      }
    }
    catch (PluginException $exception) {
      $this->moduleLogger->error('We could not load the next @entity_type type with @entity_id entity id.', [
        '@entity_type' => $entity_type,
        '@entity_id' => $entity_id,
      ]);
      // Return TRUE here so we do not throw an error in the queue worker.
      // This will prevent missing nodes from being re-processed over and over.
      return TRUE;
    }

    if (!$entity->access('view', $user)) {
      $this->moduleLogger->error('The node packager does not have access to @entity_type type with @entity_id.', [
        '@entity_type' => $entity_type,
        '@entity_id' => $entity_id,
      ]);

      return TRUE;
    }

    if ($entity_type === 'node' && !$entity->isPublished()) {
      $this->moduleLogger->error('The @entity_type type with @entity_id is not published and will not be packaged.', [
        '@entity_type' => $entity_type,
        '@entity_id' => $entity_id,
      ]);

      return TRUE;

    }

    // Set the proper base url in container.
    $entity_url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    $entity_url = $this->setProperHostName($entity_url);

    // Request cookie to reach the content which needs authentication.
    $result = $this->getCookie();
    if (!$result) {
      $this->moduleLogger->error('Could not get cookie for @entity_type type with @entity_id entity id.', [
        '@entity_type' => $entity_type,
        '@entity_id' => $entity_id,
      ]);
      return FALSE;
    }

    // We drop out the old package and create a new one.
    $this->deletePackage($entity);

    $save_folder = sprintf('%s/%s_%s', $this->fileSystem->realpath($this->packageStorageHelper->getPackageDirectory()), $entity->getEntityTypeId(), $entity->id());
    // Wget parameters:
    // --load-cookies - Load the cookies.txt
    // --no-cache - Disable server cache.
    // --quiet - Silent run, disable output.
    // --tries - Set number of retries.
    // --recursive - Recursively go to links on the page
    // --level - Depth of recursion
    // --page-requisites - Downloads all images, css, js.
    // --ignore-tags=form - Avoids rewriting the action on form actions.
    // --html-extension - If a file doesn't have any extension add .html.
    // --convert-links - Converts all link in files to local path.
    // --no-host-directories - Prevents to create a main folder for site.
    // --cut-dirs=2 - Prevents to create sub folder like node/149.
    // --accept-regex=".*\." - Download files.
    // --execute robots=off  - Prevents to download robots.txt
    // --restrict-file-names - Prevents to do any OS specify character coding.
    $wget_command = sprintf('wget --load-cookies %s \
    --user-agent="%s" \
    --no-cache \
    --tries=5 \
    --quiet \
    --page-requisites \
    --recursive \
    --level=1 \
    --ignore-tags=form \
    --html-extension \
    --convert-links \
    --no-host-directories \
    --cut-dirs=2 \
    --accept-regex=".*\." \
    --execute robots=off \
    --restrict-file-names=nocontrol \
     %s/ -P %s', $this->cookieFilePath, $this->userAgent, $entity_url, $save_folder);
    exec($wget_command, $output, $status);
    if (!$status) {
      $result = $this->createZip($entity, $save_folder);
      if ($result) {
        $this->invalidateEntityCache($entity);
        return TRUE;
      }
    }
    else {
      if (file_exists($save_folder)) {
        $this->fileSystem->deleteRecursive($save_folder);
      }

      $this->moduleLogger->warning('The wget command could not run properly on @entity @id. Status code: @code', [
        '@entity' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
        '@code' => $status,
      ]);
    }

    return FALSE;
  }

  /**
   * Save the offline page into a zip file and create a file entity for it.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A Drupal entity.
   * @param string $source_folder
   *   A folder name which will be zipped.
   *
   * @return bool
   *   Indicated that the process was successful or not.
   */
  protected function createZip(EntityInterface $entity, $source_folder) {
    $zip_path = $this->packageStorageHelper->getPackageUri($entity);
    $result = $this->generateZipFile($this->fileSystem->realpath($zip_path), $source_folder);
    if ($result) {
      $this->fileSystem->deleteRecursive($source_folder);
      $file = File::create([
        'uid' => $this->state->get('wget_user'),
        'filename' => $this->packageStorageHelper->getPackageName($entity),
        'uri' => $zip_path,
        'status' => 1,
      ]);

      $file->save();
      return TRUE;
    }
    else {
      $this->moduleLogger->error('The server could not create the zip file of @entity @id.', [
        '@entity' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
      ]);

      return FALSE;
    }
  }

  /**
   * Compress a folder into a zip file.
   *
   * @param string $zip_path
   *   The full path of the zip file.
   * @param string $folder_name
   *   The zippable folder path.
   *
   * @return bool
   *   TRUE if the generation was successful otherwise FALSE.
   */
  private function generateZipFile($zip_path, $folder_name) {
    $success = FALSE;
    $zip = new \ZipArchive();
    $zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

    // Create recursive directory iterator.
    /** @var \SplFileInfo[] $files */
    $files = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($folder_name),
      \RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
      // Skip directories (they would be added automatically)
      if (!$file->isDir()) {
        // Get real and relative path for current file.
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($folder_name) + 1);

        // Add current file to archive.
        $success = $zip->addFile($filePath, $relativePath);
      }
    }

    // Zip archive will be created only after closing object.
    $zip->close();
    return $success;
  }

  /**
   * Get a session cookie for wget command.
   *
   * @return bool
   *   TRUE if cookie generation was successful otherwise FALSE.
   */
  protected function getCookie() {
    // We need to use static variable because the user_pass_reset_url() function
    // use REQUEST_TIME for generate url, but those cases when more zip files
    // were created under one request(batch process) the session was
    // available only in the first request.
    $needs_run = &drupal_static('OFFLINE_PAGE_NEEDS_RUN');
    $previous_run = &drupal_static('OFFLINE_PAGE_PREVIOUS_RESULT');
    if (!isset($needs_run) || (!isset($needs_run) && $previous_run === 'failed')) {
      if (!empty($this->state->get('wget_user'))) {
        $login_url = user_pass_reset_url(User::load($this->state->get('wget_user')));
        $login_url = $this->setProperHostName($login_url) . '/login';
        $command = sprintf('wget --quiet --no-cache --save-cookies %s --tries=5 --post-data "op=Log In" -O /dev/null %s', $this->cookieFilePath, $login_url);
        exec($command, $output, $status);
        if ($status) {
          $this->moduleLogger->error("The wget couldn't authenticate. Status code: @status Command: @command", [
            '@status' => $status,
            '@command' => $command,
          ]);
          $previous_run = 'failed';
          return FALSE;
        }
        else {
          $previous_run = 'success';
          $needs_run = 'done';
          return TRUE;
        }
      }
      else {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Checks that package is available then delete it.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which has the zip package.
   */
  protected function deletePackage(EntityInterface $entity) {
    $zip_file = $this->packageStorageHelper->getPackageUri($entity);
    if (file_exists($zip_file)) {
      $this->packageStorageHelper->deletePackage($entity);
    }
  }

  /**
   * Set the proper base url to a content.
   *
   * We use docker where the public domains isn't available
   * but drupal doesn't know it, so we need to handle this problem by hand.
   *
   * @param string $url
   *   A full drupal url.
   *
   * @return string
   *   The proper url for a content.
   */
  private function setProperHostName($url) {
    // Firstly we test we really need this host change.
    $domain = sprintf('%s/user/login', $this->getDomain($url));
    $command = sprintf('wget --quiet %s', $domain);
    exec($command, $output, $status);
    if ($status) {
      // If we are using the wodby docker solution the php and the webserver are
      // on different containers so the php cannot reach the web server, so here
      // we need to use docker host name resolve(container name). You should set
      // the WEB_CONTAINER environment variable with the container name of web
      // server.
      if (!empty(getenv('WEB_CONTAINER'))) {
        return str_replace($this->currentRequest->getHttpHost(), getenv('WEB_CONTAINER'), $url);
      }
      else {
        return str_replace($this->currentRequest->getSchemeAndHttpHost(), 'http://localhost', $url);
      }
    }
    return $url;
  }

  /**
   * Get the domain from a normal url.
   *
   * @param string $url
   *   A full url.
   *
   * @return string
   *   The domain with scheme and port.
   */
  private function getDomain($url) {
    $url_parts = parse_url($url);
    $domain = '';

    if (isset($url_parts['scheme'])) {
      $domain .= $url_parts['scheme'] . '://';
    }

    if (isset($url_parts['host'])) {
      $domain .= $url_parts['host'];
    }

    if (isset($url_parts['port'])) {
      $domain .= ':' . $url_parts['port'];
    }

    return $domain;
  }

  /**
   * Invalidate the cache tags of an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A drupal entity.
   */
  private function invalidateEntityCache(EntityInterface $entity) {
    $tags = $entity->getCacheTags();
    if (!empty($tags)) {
      $this->cacheInvalidator->invalidateTags($tags);
    }
  }

}
