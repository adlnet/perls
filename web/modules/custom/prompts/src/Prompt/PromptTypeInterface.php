<?php

namespace Drupal\prompts\Prompt;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\user\UserInterface;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Interface for Prompt plugin type.
 */
interface PromptTypeInterface extends PluginInspectionInterface {

  /**
   * Gives back the plugin label.
   *
   * @return string
   *   Human readable name of plugin type.
   */
  public function getLabel();

  /**
   * Gibe back the plugin description.
   *
   * @return string
   *   Human readable name of plugin type.
   */
  public function getDescription();

  /**
   * Gives back the webform id.
   *
   * @return string
   *   The name of the webform what we will show the user.
   */
  public function getWebformId();

  /**
   * A time limit that how often we show the webform to the user.
   *
   * @return int
   *   The time period in hour.
   */
  public function getTimeLimit();

  /**
   * Gives back the questionField property.
   *
   * @return string
   *   The value of questionField property.
   */
  public function getQuestionField();

  /**
   * This function that user has new prompt.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   *
   * @return bool
   *   TRUE if user has prompt.
   */
  public function isTimeToAsk(UserInterface $user);

  /**
   * List of question what user will get as prompt.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user who will get the question.
   *
   * @return mixed
   *   An array of webform submission otherwise NULL;
   */
  public function getuserQuestions(UserInterface $user);

  /**
   * Create a mew webform submission.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   A drupal entity which will use as websform submission entity source.
   * @param int $uid
   *   A drupal user, who will be the owner of the submission.
   *
   * @return \Drupal\webform\Entity\WebformSubmission
   *   A new draft webform submission what the user will get.
   */
  public function generateNewQuestion(EntityInterface $source_entity, $uid);

  /**
   * Used for returning values by key.
   *
   * @var string
   *   Key of the value.
   *
   * @return string
   *   Value of the key.
   */
  public function get($key);

  /**
   * Used to create any debug test content.
   */
  public function debugInstall();

  /**
   * Used to remove any debug test content.
   */
  public function debugUninstall();

  /**
   * Used to reset data so debug testing can repeat.
   *
   * @param \Drupal\user\UserInterface $user
   *   A drupal user who will get the question.
   */
  public function debugClearData(UserInterface $user);

  /**
   * Used to take action on a webform submission.
   *
   * @param \Drupal\webform\Entity\WebformSubmission $submission
   *   A drupal user who will get the question.
   */
  public function actOnSubmission(WebformSubmission $submission);

}
