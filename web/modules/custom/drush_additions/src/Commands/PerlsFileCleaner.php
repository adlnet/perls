<?php

namespace Drupal\drush_additions\Commands;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drush\Drupal\Commands\core\MessengerCommands;

/**
 * Drush command which delete the un-used files.
 */
class PerlsFileCleaner extends MessengerCommands {

  /**
   * Stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * PerlsFileCleaner constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Drupal messenger service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   Drupal stream wrapper service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   Drupal file system service.
   */
  public function __construct(
    MessengerInterface $messenger,
    StreamWrapperManagerInterface $stream_wrapper_manager,
    FileSystemInterface $file_system) {
    parent::__construct($messenger);
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * Drush command that clean up un-used files.
   *
   * @param array $options
   *   Key value pair of options.
   *
   * @command perls:clean-files
   * @option only-check The command won't delete the files only list them.
   * @option prevent-files Here you can extend the default file list. This
   * file/folder won't be deleted. You can add more with comma.
   * @aliases scf
   * @usage perls:clean-files --only-check --prevent-files='2020-01/financial-hurdles-phase-iii-r'
   * @bootstrap full
   */
  public function fileCleanUp(array $options = [
    'only-check' => FALSE,
    'prevent-files' => '',
  ]) {
    $this->output()->writeln('Discover files.');
    $files = [];
    foreach (['public://', 'private://'] as $folder) {
      $files = array_merge($files, $this->scanFolders($folder, $options['prevent-files']));
    }
    $operations[] = [
      '\Drupal\drush_additions\FileCleaner::checkFiles',
      [
        $files,
        $options['only-check'],
      ],
    ];
    $batch = [
      'title' => t('Cleaning unused files'),
      'operations' => $operations,
      'finished' => '\Drupal\drush_additions\FileCleaner::fileCheckFinished',
    ];
    batch_set($batch);
    drush_backend_batch_process();
  }

  /**
   * List of folder where we don't want to delete the un-used files.
   */
  protected function getProtectedPath():array {
    return [
      'color',
      'badges',
      'favicons',
      'js',
      'css',
      'media-icons',
      'styles',
      'public.key',
      'private.key',
      '.htaccess',
    ];
  }

  /**
   * Generate command part which list protected folders.
   *
   * @param string $extra_path
   *   A list of extra folders what user add to default list.
   *
   * @return string
   *   Folder list part of file listing command.
   */
  protected function generateProtectedPathList($extra_path = '') {
    $folder_list = $this->getProtectedPath();
    $command = '';
    if (!empty($extra_path)) {
      $folder_list = array_merge($folder_list, array_map('trim', explode(',', $extra_path)));
    }

    foreach ($folder_list as $folder_name) {
      $command .= sprintf('! -path "*/%s*" ', $folder_name);
    }

    return $command;
  }

  /**
   * Recursively scan folder and collect all files.
   *
   * @param string $path
   *   Folder path. This should be absolute path or and uri.
   * @param string $prevent_folders
   *   A list of folder where we want to avoid scanning. Comma separated.
   *
   * @return array
   *   Files in the folder.
   */
  protected function scanFolders($path, $prevent_folders): array {
    if ($this->streamWrapperManager->isValidUri($path)) {
      $path = $this->fileSystem->realpath($path);
    }
    $command = sprintf('cd %s && find "$(pwd)" %s', $path, $this->generateProtectedPathList($prevent_folders));
    $command = shell_exec($command);
    if (isset($command) && !empty($command)) {
      // List of files of "files" folder.
      // File names are in absolute path format.
      $files = explode("\n", $command);
    }
    return $files;
  }

}
