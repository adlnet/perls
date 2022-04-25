<?php

namespace Drupal\perls_xapi_reporting\EventSubscriber;

use Drupal\flag\Entity\Flag;
use Drupal\flag\FlagServiceInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\xapi\Event\XapiStatementReceived;
use Drupal\xapi\XapiStatementHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Monitors for completion of activities.
 *
 * Currently only is watching for completion of eLearning Packages
 * (though the logic can be applied to any entity).
 *
 * Long term, this should ideally be handled via configuration:
 * a statement pattern to watch for and an action to perform when
 * the pattern occurs.
 */
class ActivityCompletionMonitor implements EventSubscriberInterface {
  use LoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * Convenience methods for interacting with xAPI statements.
   *
   * @var \Drupal\xapi\XapiStatementHelper
   */
  protected $statementHelper;

  /**
   * Flagging service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagger;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new ActivityCompletionMonitor object.
   */
  public function __construct(XapiStatementHelper $statement_helper, FlagServiceInterface $flagger, AccountProxyInterface $current_user) {
    $this->statementHelper = $statement_helper;
    $this->flagger = $flagger;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      XapiStatementReceived::EVENT_NAME => ['onStatementReceived', -9999],
    ];
  }

  /**
   * Invoked when a new statement is sent to the LRS proxy.
   *
   * @param \Drupal\xapi\Event\XapiStatementReceived $event
   *   The dispatched event.
   */
  public function onStatementReceived(XapiStatementReceived $event) {
    $statement = $event->getStatement();

    // Filter out statements which don't include a completion result.
    if (!isset($statement->result) || !isset($statement->result->completion) || $statement->result->completion !== TRUE) {
      return;
    }

    // Filter out statements where the result was explicitly unsuccessful.
    // Since Perls does not have the notion of unsuccessful vs successful,
    // we'll simply consider "unsuccessful" as "incomplete.".
    if (isset($statement->result->success) && !$statement->result->success) {
      return;
    }

    // Since we can't control eLearning packages and how they report
    // completion, we ensure broadest compatibility by watching for the
    // statement result to indicate completion and we can update the
    // cooresponding flag on the eLearning package.
    // For now, only watch for eLearning packages--but this could be expanded
    // in the future to handle all content types.
    $entity = $this->statementHelper->getEntityFromStatement($statement);
    if (!$entity || $entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'learn_package') {
      return;
    }

    $user = $this->statementHelper->getUserFromStatement($statement);
    if (!$user) {
      return;
    }

    // Only allow the completion to be set if the user is the current user OR
    // the user has permission to set completions for other users.
    if ($user->id() !== $this->currentUser->id() && !$this->currentUser->hasPermission('set user completions')) {
      return;
    }

    $flag = $this->getFlag();

    // Verify the flag supports the completed entity type.
    $bundles = $flag->getBundles();
    if ($flag->getFlaggableEntityTypeId() != $entity->getEntityTypeId()
      || !empty($bundles) && !in_array($entity->bundle(), $bundles)
      || $flag->isFlagged($entity, $user)) {
      return;
    }

    try {
      $flagging = $this->flagger->flag($flag, $entity, $user);

      $link = Link::createFromRoute($this->t('view completions'), 'view.administrate_user_flags.administer_user_flags_completed', ['user' => $user->id()]);

      $this->getLogger('xapi')->info("%entity was completed for %user with statement:\n<pre>@statement</pre>", [
        '%entity' => $entity->label(),
        '%user' => $user->label(),
        '@statement' => json_encode($statement, JSON_PRETTY_PRINT),
        'link' => $link->toString(),
      ]);
    }
    catch (\LogicException $e) {
      watchdog_exception('xapi', $e);
    }
  }

  /**
   * Get the flagging representing completion.
   *
   * @return \Drupal\flag\Entity\Flag
   *   The flag representing completion.
   */
  protected function getFlag(): Flag {
    return $this->flagger->getFlagById('completed');
  }

}
