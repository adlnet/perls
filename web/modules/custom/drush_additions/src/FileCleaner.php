<?php

namespace Drupal\drush_additions;

use Drupal\Core\File\Exception\FileException;

/**
 * Methods of files cleaning batch process.
 */
class FileCleaner {

  /**
   * Check the file usage in cms. Delete the un-used files.
   *
   * @param array $files
   *   List of files in files folder.
   * @param bool $only_check
   *   If this value set to TRUE the script doesn't delete the file.
   * @param object $context
   *   Batch context.
   */
  public function checkFiles(array $files, bool $only_check, &$context) {
    /** @var \Drupal\Core\File\FileSystem $file_system */
    $file_system = \Drupal::service('file_system');
    if (empty($context['sandbox']['total'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['total'] = count($files);
      $context['sandbox']['files'] = $files;
      $context['sandbox']['failed'] = 0;
    }
    $limit = 10;
    $current_files = array_slice($context['sandbox']['files'], $context['sandbox']['progress'], $limit);
    foreach ($current_files as $path) {
      // Some file name has spaces, but it breaks the commands in Linux.
      $file_path = str_replace(' ', '\ ', trim($path));
      if (empty($path)) {
        continue;
      }

      if (is_dir($file_path) &&
        !count(array_diff(scandir($file_path), ['..', '.']))
        && !$only_check) {
        self::deleteEmptyFolder($file_path);
      }
      elseif (!is_dir($file_path)) {
        $pathToUri = self::pathToUri($path);
        if (empty($pathToUri)) {
          return;
        }
        $file = \Drupal::service('entity_type.manager')
          ->getStorage('file')
          ->loadByProperties(['uri' => $pathToUri]);
        if ($file) {
          $file = reset($file);
          /** @var \Drupal\file\FileUsage\DatabaseFileUsageBackend $fileService */
          $fileService = \Drupal::service('file.usage');
          $usage = $fileService->listUsage($file);
          if (!$usage) {
            if (!$only_check) {
              $file->delete();
              \Drupal::messenger()
                ->addStatus(t('The @file file was deleted.', [
                  '@file' => $file->label(),
                ]));
            }
            else {
              \Drupal::messenger()->addStatus(t('The @file is not used.', [
                '@file' => $file->label(),
              ]));
            }
          }
        }
        else {
          if (!$only_check) {
            try {
              $file_system->delete($file_path);
              \Drupal::messenger()
                ->addStatus(t('The @file file was deleted.', [
                  '@file' => $path,
                ]));
            }
            catch (FileException $exception) {
              \Drupal::messenger()
                ->addWarning(t('The server could not delete the @file file. Error: @error', [
                  '@file' => $path,
                  '@error' => $exception->getMessage(),
                ]));
            }
          }
          else {
            \Drupal::messenger()
              ->addStatus(t('The @file is not tracked by drupal.', ['@file' => $path]));
          }
        }
      }
    }
    $context['sandbox']['progress'] += $limit;
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['total'];
  }

  /**
   * Finish method of file cleaning batch process.
   */
  public function fileCheckFinished() {
    \Drupal::messenger()->addStatus(t('The file cleaning has been finished.'));
  }

  /**
   * Delete the empty folders.
   *
   * @param string $folder_path
   *   Folder full path.
   */
  protected function deleteEmptyFolder($folder_path) {
    /** @var \Drupal\Core\File\FileSystem $file_system */
    $file_system = \Drupal::service('file_system');
    try {
      $file_system->deleteRecursive($folder_path);
      \Drupal::messenger()->addStatus(t('Deleted successfully the @folder folder', ['@folder' => $folder_path]));
    }
    catch (FileException $exception) {
      \Drupal::messenger()->addError(t('An error occurred during delete of @folder folder. @error', [
        '@folder' => $folder_path,
        '@error' => $exception->getMessage(),
      ]));
    }
  }

  /**
   * Contains path pairs to public and private scheme.
   *
   * @return string[]
   *   Full path of folder on server => scheme.
   */
  protected static function getUriBasePathSet() {
    /** @var \Drupal\Core\File\FileSystem $file_system */
    $file_system = \Drupal::service('file_system');
    return [
      $file_system->realpath('public://') => 'public://',
      $file_system->realpath('private://') => 'private://',
    ];
  }

  /**
   * Change a full path url to uri.
   *
   * @param string $file_path
   *   The absolute path of file.
   *
   * @return string
   *   The file URI.
   */
  protected static function pathToUri($file_path) {
    $uri_list = self::getUriBasePathSet();
    foreach ($uri_list as $uri_path => $uri_base) {
      if (strpos($file_path . '/', $uri_path) !== FALSE) {
        return str_replace($uri_path . '/', $uri_base, $file_path);
      }
    }

    return '';
  }

}
