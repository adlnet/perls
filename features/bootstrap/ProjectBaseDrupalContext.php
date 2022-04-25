<?php

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Mink\Driver\GoutteDriver;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Drupal\DrupalExtension\Context\DrupalContext;

/**
 * Defines application features from the specific context.
 */
class ProjectBaseDrupalContext extends DrupalContext implements SnippetAcceptingContext {

  /**
   * @BeforeScenario
   */
  public function hookBeforeScenario() {
    try {
      $this->visitPath('/');
    }
    catch (\Exception $e) {
      throw new \Exception('The test cannot reach the homepage. More details:' . $e->getMessage());
    }
  }

  /**
   * @Given I am manually logged in as :username with :password
   */
  public function iAmManuallyLoggedInAs($username, $password) {
    $element = $this->getSession()->getPage()->find('xpath', '//a[@href="/user/logout"]');
    if (!empty($element)) {
      $element->click();
    }
    $this->getSession()->visit($this->locatePath('user/login'));
    $this->getSession()->getPage()->fillField($this->getDrupalText('username_field'), $username);
    $this->getSession()->getPage()->fillField($this->getDrupalText('password_field'), $password);
    $element = $this->getSession()->getPage();
    $submit = $element->findButton($this->getDrupalText('log_in'));
    if (empty($submit)) {
      throw new \Exception(sprintf("No submit button at %s", $this->getSession()->getCurrentUrl()));
    }

    // Log in.
    $submit->click();
  }

  /**
   * Creates and authenticates a user with email address and the given role(s).
   *
   * @Given I am logged in with email address as a user with the :role role(s)
   * @Given I am logged in with email address as a/an :role
   */
  public function assertAuthenticatedByRoleWithEmail($role) {
    // Check if a user with this role is already logged in.
    if (!$this->loggedInWithRole($role)) {
      // Create user (and project)
      $user = (object) [
        'name' => "{$this->getRandom()->name(8)}@example.com",
        'pass' => $this->getRandom()->name(16),
        'role' => $role,
      ];
      $user->mail = $user->name;

      $this->userCreate($user);

      $roles = explode(',', $role);
      $roles = array_map('trim', $roles);
      foreach ($roles as $role) {
        if (!in_array(strtolower($role), [
          'authenticated',
          'authenticated user',
        ])) {
          // Only add roles other than 'authenticated user'.
          $this->getDriver()->userAddRole($user, $role);
        }
      }

      // Login.
      $this->login($user);
    }
  }

  /**
   * @Then I should manually logout
   */
  public function iShouldManuallyLogout() {
    $element = $this->getSession()->getPage()->find('xpath', '//a[@href="/user/logout"]');

    if (empty($element)) {
      throw new ExpectationException('Could not find logout button');
    }
    $element->click();
  }

  /**
  * @Given /^I scroll to x "([^"])" y "([^"])" coordinates of page$/
  */
  public function iScrollToXYCoordinatesOfPage($arg1, $arg2) {
    try {
      $this->getSession()->executeScript("(function(){window.scrollTo($arg1, $arg2);})();");
    }
    catch (\Exception $e) {
      throw new \Exception("ScrollIntoView failed");
    }
  }

  /**
   * Wait for AJAX to finish.
   *
   * @Given /^I wait for AJAX to finish$/
   */
  public function iWaitForAjaxToFinish() {

    $this->getSession()->wait(3000);
  }

  /**
   * {@inheritdoc}
   */
  public function afterJavascriptStep($event) {
    /** @var \Behat\Behat\Hook\Scope\BeforeStepScope $event */

    if ($this->getSession()->getDriver() instanceof GoutteDriver) {
      return;
    }

    // In most user intereactions, wait for AJAX to finish, if necessary.
    $text = $event->getStep()->getText();
    if (preg_match('/(follow|press|click|submit|step out|select|attach)/i', $text)) {
      try {
        $this->iWaitForAjaxToFinish();
      }
      catch (\RuntimeException $e) {
        if (!empty($event)) {
          /** @var \Behat\Behat\Hook\Scope\BeforeStepScope $event */
          $event_data = ' ' . json_encode([
            'name' => $event->getName(),
            'feature' => $event->getFeature()->getTitle(),
            'step' => $event->getStep()->getText(),
            'suite' => $event->getSuite()->getName(),
          ]);
        }
        else {
          $event_data = '';
        }
        $this->getSession()->wait(2000);
        throw new \RuntimeException('Unable to complete AJAX request. Event info: ' . $event_data);
      }
    }
  }

  /**
   * @Then I should see :arg1 exactly :arg2 times
   */
  public function iShouldSeeExactlyTimes($arg1, $arg2) {
    $sContent = $this->getSession()->getPage()->getText();
    $iFound = substr_count($sContent, $arg1);
    if ($arg2 != $iFound) {
      throw new \Exception('Found ' . $iFound . ' occurences of "' . $arg1 . '" when expecting ' . $arg2);
    }
  }

  /**
   * @Given I click the element :arg1
   */
  public function iClickTheElement($arg1) {
    $page = $this->getSession()->getPage();
    $element = $page->find('css', $arg1);

    if (empty($element)) {
      throw new \Exception("No html element found for the selector ('$arg1')");
    }

    $element->click();
  }

  /**
   * Selects option in select field with specified id|name|label|value
   *
   * @When /^(?:|I )select the superuser from "(?P<select>(?:[^"]|\\")*)"$/
   */
  public function selectTheRecentlyAddedSuperUserOption($select) {
    $name = "";
    if ($this->userManager->hasUsers()) {
      foreach ($this->userManager->getUsers() as $user) {
        if ($user->role == 'superuser') {
          $name = $user->name;
        }
      }
    }
    $this->getSession()->getPage()->selectFieldOption($select, $name);
  }

  /**
   * @Given I am logged in as the recently created user with the :role role
   */
  public function iAmLoggedInAsTheRecentlyCreatedUserWithTheRole($role) {
    if ($this->userManager->hasUsers()) {
      foreach ($this->userManager->getUsers() as $user) {
        // Every user who exists is authenticated so we don't need to check.
        if (in_array($role, [
          'authenticated',
          'authenticated user',
        ]) || $user->role === $role) {
          $this->login($user);
          return;
        }
      }
    }
  }

  /**
   * @Given I save a screenshot to :arg1
   */
  public function iSaveAScreenshotTo($arg1) {
    $image_data = $this->getSession()->getDriver()->getScreenshot();
    $file_and_path = $this->getMinkParameter('files_path') . '/artifacts/screenshots/' . $arg1;
    file_put_contents($file_and_path, $image_data);
  }

  /**
   * @Given I fill in wysiwyg on field :locator with :value
   */
  public function iFillInWysiwygOnFieldWith($value, $locator) {
    $el = $this->getSession()->getPage()->findField($locator);

    if (empty($el)) {
      throw new ExpectationException('Could not find WYSIWYG with locator: ' . $locator, $this->getSession());
    }

    $fieldId = $el->getAttribute('id');

    if (empty($fieldId)) {
      throw new \Exception('Could not find an id for field with locator: ' . $locator);
    }

    $this->getSession()
      ->executeScript("CKEDITOR.instances[\"$fieldId\"].setData(\"$value\");");
  }

  /**
   * @Given I choose :arg1 after entering :arg2 in the autocomplete :arg3
   */
  public function iChooseAfterEnteringInTheAutocomplete($popup, $text, $field) {
    $session = $this->getSession();
    $element = $session->getPage()->findField($field);
    if (empty($element)) {
      throw new ElementNotFoundException($session, NULL, 'named', $field);
    }
    $element->setValue($text);
    $element->focus();
    $this->iWaitForAjaxToFinish();
    $available_autocompletes = $this->getSession()->getPage()->findAll('css', 'ul.ui-autocomplete[id^=ui-id]');
    if (empty($available_autocompletes)) {
      throw new ElementNotFoundException('Could not find the autocomplete popup box');
    }
    // It's possible for multiple autocompletes to be on the page at once,
    // but it shouldn't be possible for multiple to be visible/open at once.
    foreach ($available_autocompletes as $autocomplete) {
      if ($autocomplete->isVisible()) {
        $matches = $autocomplete->findAll('xpath', "//li/a");
        if (empty($matches)) {
          throw new \Exception(t('Could not find the select box'));
        }
        foreach ($matches as $match) {
          if ($match->getText() == $popup) {
            $match->click();
            return;
          }
        }
      }
    }
    throw new \Exception('Could not find the autocomplete popup box');
  }

  /**
   * @Given I choose :arg1 after entering :arg2 in the _chosen_ field with id :arg3
   */
  public function iChooseAfterEnteringInTheChosenFieldWithId($popup, $text, $field) {
    $session = $this->getSession();
    $element = $session->getPage()->findField($field);
    if (empty($element)) {
      throw new ElementNotFoundException($session, NULL, 'named', $field);
    }
    $element->focus();
    $element->setValue($text);
    $this->getSession()->wait(3000);
    $field_replace = str_replace("-", "_", $field);
    $matches = $this->getSession()->getPage()->findAll('xpath', '//div[@id="' . $field_replace . '_chosen"]/div/ul/li');
    if (empty($matches)) {
      throw new \Exception('Could not find the select box');
    }
    foreach ($matches as $match) {
      if ($match->getText() == $popup) {
        $match->click();
        return;
      }
    }
    throw new \Exception('Could not find the select box');
  }

  /**
   * @Then I select :arg1 from field :arg2
   */
  public function iSelectFromField($value, $locator) {
    $session = $this->getSession();
    $field = $session->getPage()->findField($locator);
    if (NULL === $field) {
      throw new ElementNotFoundException($this->getSession(), 'form field', 'id|name|label|value', $locator);
    }
    $field->selectOption($value);
  }

  /**
   * @Given the default theme is :arg1
   * @When I set the default theme to :arg1
   */
  public function setDefaultTheme($theme) {
    $config = \Drupal::service('config.factory')->getEditable('system.theme');
    $config->set('default', $theme)->save();
  }

  /**
   * @Given I wait for the batch job to finish
   */
  public function iWaitForTheBatchJobToFinish() {
    $this->getSession()->wait(180000, '!document.getElementById("updateprogress")');
  }

  /**
   * @Given I select all rows in the table
   */
  public function iSelectAllRowsInTable() {
    $this->getSession()->getPage()->find('css', 'th.select-all .form-checkbox')->click();
  }

}
