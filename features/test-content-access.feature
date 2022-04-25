@core_functionality
Feature: Test proper access handling if a test content is part of a course content.

  Background:
    Given "category" terms:
      | name      |
      | eLearning |
    Given the following content:
      """
      title: Test publishing quiz
      type: quiz
      langcode: en
      field_topic: eLearning
      """
    And the following content:
      """
      title: Test publishing test
      type: test
      langcode: en
      field_quiz: Test publishing quiz
      field_pass_mark: 85
      """
    And the following content:
      """
      title: Test publishing course
      type: course
      langcode: en
      field_topic: eLearning
      field_learning_content: Test publishing test
      """

  @javascript @api
  Scenario: I create a new course
    When I am logged in with email address as a user with the "authenticated user" role
    And I am visiting the "course" content "Test publishing course"
    Then I should see the text "Test publishing course"

  @javascript @api
  Scenario: An learner doesn't have access to content and test if content is unpublished.
    Given I am logged in with email address as a user with the "Content Manager" role
    When I edit the "course" content "Test publishing course"
    Then I uncheck the box "Published"
    When I submit the content form
    Then I should see the text "has been updated"
    Then I am logged in with email address as a user with the "authenticated user" role
    And I am visiting the "course" content "Test publishing course"
    And I should get an access denied error
    Then I am visiting the "test" content "Test publishing test"
    And I should get an access denied error

  @javascript @api
  Scenario: An learner have access to course but he doesn't have access to unpublished test.
    Given I am logged in with email address as a user with the "Content Manager" role
    When I edit the "test" content "Test publishing test"
    Then I uncheck the box "Published"
    When I submit the content form
    Then I should see the text "has been updated"
    Then I am logged in with email address as a user with the "authenticated user" role
    And I am visiting the "course" content "Test publishing course"
    And I should see the text "Test publishing course"
    Then I am visiting the "test" content "Test publishing test"
    And I should get an access denied error




