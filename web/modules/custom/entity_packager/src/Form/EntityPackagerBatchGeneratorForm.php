<?php

namespace Drupal\entity_packager\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_packager\NodePackageBatchGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class OfflinePageBatchGenerateForm.
 */
class EntityPackagerBatchGeneratorForm extends FormBase {

  /**
   * The offline page generator service.
   *
   * @var \Drupal\entity_packager\NodePackageBatchGenerator
   */
  protected $batch;

  /**
   * Add a small form where user can start a batch offline page generation.
   *
   * @param \Drupal\entity_packager\NodePackageBatchGenerator $batch
   *   A helper class to create batch process.
   */
  public function __construct(NodePackageBatchGenerator $batch) {
    $this->batch = $batch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
       $container->get('entity_packager.batch_generator')
     );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_package_generate_batch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['generate_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate node packages.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->batch->generate();
  }

}
