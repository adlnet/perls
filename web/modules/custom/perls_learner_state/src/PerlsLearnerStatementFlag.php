<?php

namespace Drupal\perls_learner_state;

use Drupal\Core\Entity\EntityInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\perls_learner_state\Plugin\XapiStateManager;
use Drupal\user\UserInterface;
use Drupal\xapi\XapiStatementHelper;

/**
 * This service help to store those statement data which related to flag.
 */
class PerlsLearnerStatementFlag {

  /**
   * Manager of perls xapi statement types.
   *
   * @var \Drupal\perls_learner_state\Plugin\XapiStateManager
   */
  protected XapiStateManager $stateManager;

  /**
   * An xapi statement helper.
   *
   * @var \Drupal\xapi\XapiStatementHelper
   */
  protected $xapiStatementHelper;

  /**
   * Helper service from flag module.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected FlagServiceInterface $flagService;

  /**
   * Help to manage flag related statements.
   *
   * @param \Drupal\perls_learner_state\Plugin\XapiStateManager $state_manager
   *   Manager of custom statement types.
   * @param \Drupal\xapi\XapiStatementHelper $statement_helper
   *   A Service which helps to manage xapi statement.
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   Flag service to manage flag entities.
   */
  public function __construct(
    XapiStateManager $state_manager,
    XapiStatementHelper $statement_helper,
    FlagServiceInterface $flag_service) {
    $this->stateManager = $state_manager;
    $this->xapiStatementHelper = $statement_helper;
    $this->flagService = $flag_service;
  }

  /**
   * Sync flags in drupal.
   *
   * @param \Drupal\Core\Entity\EntityInterface $content
   *   A drupal content.
   * @param \Drupal\Core\Entity\EntityInterface $user
   *   A drupal user.
   * @param array $verb
   *   A parsed verb object from statement.
   * @param string $timestamp
   *   An optional value of flagging timestamp.
   * @param object $statement
   *   The Xapi statement triggering this method.
   */
  public function flagSync(EntityInterface $content, EntityInterface $user, array $verb, $timestamp = NULL, $statement = NULL) {
    if (!empty($user) && !empty($content) && $this->flagOperation($verb['verb_url']) === 'add') {
      $extra_flag_data = [];
      if (isset($timestamp)) {
        $extra_flag_data['created'] = $timestamp;
      }
      foreach ($verb['flag_plugins'] as $plugin) {
        $plugin->flagSync($content, $user, $extra_flag_data, $statement);
      }
    }
    elseif (!empty($user) && !empty($content) && $this->flagOperation($verb['verb_url']) === 'remove') {
      foreach ($verb['flag_plugins'] as $id => $plugin) {
        $plugin->unflag($content, $user);
      }
    }
  }

  /**
   * Decides based on a verb ulr that's an add flag or remove flag verb.
   *
   * @param string $verb
   *   A verb url.
   *
   * @return string
   *   It can be none, add, remove output.
   */
  public function flagOperation($verb) {
    $stage_definitions = $this->stateManager->getDefinitions();
    foreach ($stage_definitions as $definition) {
      if (isset($definition['add_verb']) &&
        $definition['add_verb']->getId() !== NULL &&
        $definition['add_verb']->getId() === $verb) {
        return 'add';
      }
      elseif (isset($definition['remove_verb']) &&
        $definition['remove_verb']->getId() !== NULL &&
        $definition['remove_verb']->getId() === $verb) {
        return 'remove';
      }
    }

    return 'none';
  }

  /**
   * Sync flag statement with Drupal.
   *
   * @param object $statement
   *   A statement object.
   */
  public function flagStatementSync($statement) {
    $user = $this->xapiStatementHelper->getUserFromStatement($statement);
    $flag_data = $this->getFlagOperationFromStatement($statement);
    $node = $this->xapiStatementHelper->getContentFromState($statement);
    if (!empty($statement->timestamp)) {
      $flag_timestamp = strtotime($statement->timestamp);
    }
    else {
      $flag_timestamp = time();
    }
    $this->flagSync($node, $user, $flag_data, $flag_timestamp, $statement);
  }

  /**
   * Parse the verb url in statement and gives back the last part.
   *
   * @param object $statement
   *   A xApi statement array.
   *
   * @return array
   *   The parsed verb which has two key verb_url and flag, display.
   */
  public function getFlagOperationFromStatement($statement) {
    $verb_data = [];
    if (!empty($statement->verb) && !empty($statement->verb->id)) {
      $verb = $statement->verb;
      $verb_data['verb_url'] = $verb->id;
      $verb_data['flag_plugins'] = $this->verbFlagMapping($verb->id);
    }
    return $verb_data;
  }

  /**
   * Flagging a content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $content
   *   The flagable node.
   * @param string $flag
   *   The flag name.
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   * @param array $extraData
   *   Extra data.
   * @param bool $appendData
   *   Append the data or not.
   *
   * @return \Drupal\flag\FlaggingInterface|null
   *   The flagging object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createNewFlag(EntityInterface $content, $flag, UserInterface $user = NULL, array $extraData = [], $appendData = FALSE) {
    $flag = $this->flagService->getFlagById($flag);
    if ($flag) {
      // We check that this flagging is exist or not.
      $flagging = $this->flagService->getFlagging($flag, $content, $user);

      if (!$flagging) {
        $flagging = $this->flagService->flag($flag, $content, $user);
      }

      foreach ($extraData as $key => $value) {
        $newValue = $value;
        if ($appendData) {
          $newValue = $flagging->get($key)->getValue();
          $newValue[] = $value;
        }

        $flagging->set($key, $newValue);
      }

      $flagging->save();

      return $flagging;
    }

    return NULL;
  }

  /**
   * Flagging a content once.
   *
   * @param \Drupal\Core\Entity\EntityInterface $content
   *   The flagable node.
   * @param string $flag
   *   The flag name.
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   * @param array $extraData
   *   Extra data.
   *
   * @return \Drupal\flag\FlaggingInterface|null
   *   The flagging object.
   */
  public function createNewFlagOnce(EntityInterface $content, $flag, UserInterface $user = NULL, array $extraData = []) {
    $flag = $this->flagService->getFlagById($flag);
    if ($flag) {
      // We check that this flagging is exist or not.
      $flagging = $this->flagService->getFlagging($flag, $content, $user);
      if (!$flagging) {
        $flagging = $this->flagService->flag($flag, $content, $user);
        foreach ($extraData as $key => $value) {
          $flagging->set($key, $value);
        }
        $flagging->save();
        return $flagging;
      }
      else {
        return $flagging;
      }
    }

    return NULL;
  }

  /**
   * Delete flagging.
   *
   * @param \Drupal\Core\Entity\EntityInterface $content
   *   The flagable node.
   * @param string $flag
   *   The flag name.
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   */
  public function deleteFlag(EntityInterface $content, $flag, UserInterface $user = NULL) {
    $flag = $this->flagService->getFlagById($flag);
    if ($flag) {
      // We check that this flagging is exist or not.
      $flagging = $this->flagService->getFlagging($flag, $content, $user);
      if ($flagging) {
        $this->flagService->unflag($flag, $content, $user);
      }
    }
  }

  /**
   * Get the flag that cooresponds to the specified verb.
   *
   * @param string $verb_id
   *   The verb ID.
   *
   * @return array
   *   The flag id.
   */
  public function verbFlagMapping($verb_id) {
    $state_definitions = $this->stateManager->getDefinitions();
    $state_plugins = [];
    foreach ($state_definitions as $id => $definition) {
      if (isset($definition['add_verb']) &&
        $definition['add_verb']->getId() !== NULL &&
        $definition['add_verb']->getId() === $verb_id) {
        if ($definition['notifyOnXapi'] === TRUE) {
          $state_plugins[$id] = $this->stateManager->createInstance($id);
        }

      }
      elseif (isset($definition['remove_verb']) &&
        $definition['remove_verb']->getId() !== NULL &&
        $definition['remove_verb']->getId() === $verb_id) {
        if ($definition['notifyOnXapi'] === TRUE) {
          $state_plugins[$id] = $this->stateManager->createInstance($id);
        }
      }
    }

    return $state_plugins;
  }

}
