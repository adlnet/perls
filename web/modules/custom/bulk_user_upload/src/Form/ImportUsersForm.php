<?php

namespace Drupal\bulk_user_upload\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\bulk_user_upload\BulkUserUpload;

/**
 * Form for bulk importing users.
 *
 * As part of validation, the uploaded file is parsed.
 */
class ImportUsersForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'importusers';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $roles = BulkUserUpload::availableRoles();
    $role_options = [];
    foreach ($roles as $key => $role) {
      $role_options[$key] = $role->label();
    }

    $form['template'] = [
      '#type' => 'item',
      '#title' => $this->t('CSV Template'),
    ];
    $form['template']['download'] = [
      '#type' => 'html_tag',
      '#tag' => 'a',
      '#value' => $this->t('Download'),
      '#attributes' => [
        'href' => Url::fromUri('internal:/' . drupal_get_path('module', 'bulk_user_upload') . '/src/file/user-import.csv')->toString(),
        'class' => ['o-button--small'],
      ],
    ];

    $form['help'] = [
      '#title' => $this->t('Instructions'),
      '#type' => 'details',
    ];

    $roles_help = [
      '#theme' => 'item_list',
      '#items' => $role_options,
    ];

    $form['help']['content'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('You can use a CSV (comma-separated values) file to import multiple users.'),
    ];

    $form['help']['columns'] = [
      '#theme' => 'table',
      '#header' => [
        $this->t('Column'),
        $this->t('Description'),
      ],
      '#rows' => [
        [
          'Email',
          $this->t("<strong>Required.</strong> The user's email address; they will use this for logging in. Users are identified by their email address. The row will be skipped if there is already a user with the email address."),
        ],
        [
          'Name',
          $this->t('The full name of the user.'),
        ],
        [
          'Password',
          $this->t('The password for the user. If blank, the user can use "Forgot Password" to log in for the first time.'),
        ],
        [
          'Role',
          $this->t('The role to assign to the user. If blank, the <strong>Default Role</strong> specified below will be used. Options are: @role_options', [
            '@role_options' => render($roles_help),
          ]),
        ],
      ],
    ];

    $form['file_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('CSV file'),
      '#autoupload' => TRUE,
      '#required' => TRUE,
      '#upload_location' => 'temporary://imports',
      '#upload_validators' => ['file_validate_extensions' => ['csv']],
    ];

    $form['default_role'] = [
      '#options' => $role_options,
      '#type' => 'select',
      '#title' => $this->t('Default role'),
      '#description' => $this->t('Unless a different role is specified in the CSV, new users will be assigned this role during import.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $fid = $form_state->getValue(['file_upload', 0]);

    if (empty($fid)) {
      return;
    }

    $file = File::load($fid);
    $rows = array_map('str_getcsv', file($file->getFileUri()));

    if (empty($rows)) {
      $this->handleFileValidationError($form['file_upload'], $form_state, $this->t('The CSV file is not valid: the <code>Email</code> column is missing.'));
      return;
    }

    $header = array_map('strtolower', array_shift($rows));

    if (array_search('email', $header) === FALSE) {
      $this->handleFileValidationError($form['file_upload'], $form_state, $this->t('The CSV file is not valid: the <code>Email</code> column is missing.'));
      return;
    }

    $form_state->setValue('csv_header', $header);
    $form_state->setValue('csv_rows', $rows);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $header = $form_state->getValue('csv_header');
    $rows = $form_state->getValue('csv_rows');

    $operations = [];

    // Create batch operation chunks of 5.
    $batch_array = [];
    foreach ($rows as $key => $row) {
      if (count($batch_array) >= 5) {
        $operations[] = ['\Drupal\bulk_user_upload\BulkUserUpload::uploadUserProcess',
          [$batch_array, $form_state->getValue('default_role')],
        ];
        $batch_array = [];
      }

      $record = [];
      foreach ($header as $i => $column) {
        $record[$column] = $row[$i] ?? NULL;
      }

      // Add two to offset for 0-index and header.
      $record['row_number'] = $key + 2;

      $batch_array[] = $record;
    }

    if (count($batch_array)) {
      $operations[] = ['\Drupal\bulk_user_upload\BulkUserUpload::uploadUserProcess',
        [$batch_array, $form_state->getValue('default_role')],
      ];
    }

    // Setup the batch job.
    $batch = [
      'title' => $this->t('Import Users from CSV'),
      'operations' => $operations,
      'finished' => '\Drupal\bulk_user_upload\BulkUserUpload::uploadUserFinishedCallback',
      'init_message' => $this->t('Parsing CSV...'),
      'progress_message' => $this->t('Importing...'),
      'error_message' => $this->t('Bulk User Import has encountered an error.'),
    ];

    // Trigger the batch job.
    batch_set($batch);
  }

  /**
   * Handles an error processing an uploaded file.
   *
   * @param array $element
   *   The element where the file was uploaded.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $error
   *   The error message to show to the user.
   */
  protected function handleFileValidationError(array &$element, FormStateInterface $form_state, $error) {
    $fid = reset($element['#value']['fids']);
    if ($fid) {
      unset($element['file_' . $fid]);
    }

    $element['fids'] = [];
    $element['#value']['fids'] = [];
    $element['#files'] = [];

    $form_state->setError($element, $error);
  }

}
