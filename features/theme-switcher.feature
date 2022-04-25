@core_functionality
Feature: Test theme switcher functionality

  Background:
    Given "category" terms:
      | name      |
      | eLearning |
    Given the following content:
      """
      title: Test learn article
      type: learn_article
      langcode: en
      field_topic: eLearning
      moderation_state: published
      field_body:
        -
          type: text
          field_paragraph_body: Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec et ex risus.
      """

  @api @javascript
  Scenario: As an authenticated user I use learner theme as default.
    Given I am logged in with email address as a user with the "authenticated user" role
    When I go to the homepage
    Then the "body" element should contain "perls-learner"
    When I am at "/user"
    Then the "body" element should contain "perls-learner"

  @api @javascript
  Scenario: As a content manager user I use content manager theme on specific path.
    Given I am logged in with email address as a user with the "Content Manager" role
    When I am at "/manage-content/course"
    Then the "body" element should contain "perls-content-manager"
    And I am visiting the "learn_article" content "Test learn article"
    Then the "body" element should contain "perls-learner"
    Then I click "Edit"
    Then the "body" element should contain "perls-content-manager"
    When I am at "/manage/courses-and-content-library/topics"
    Then the "body" element should contain "perls-content-manager"

  @api @javascript
  Scenario: As an administrator user I use admin theme on admin paths.
    Given I am logged in with email address as a user with the "Sysadmin" role
    When I am at "/manage-content/course"
    Then the "body" element should contain "perls-content-manager"
    And I am visiting the "learn_article" content "Test learn article"
    Then the "body" element should contain "perls-learner"
    When I am at "/admin"
    Then the "body" element should contain "adminimal-theme"


