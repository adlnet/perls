@core_functionality
Feature: Verify the home page experience is correct.

  @javascript @api
  Scenario: Learner gets recommendations page as the homepage
    Given I am logged in with email address as a user with the "authenticated user" role
    When I go to the homepage
    Then the "body" element should contain "perls-learner"
    Then the url should match "/start"

  @javascript @api
  Scenario: Content manager gets dashboard page as the homepage
    Given I am logged in with email address as a user with the "Content Manager" role
    When I go to the homepage
    Then the "body" element should contain "perls-content-manager"
    And I should see "Dashboard"

  @javascript @api
  Scenario: Anonymous user gets appropriate user/login page.
    Given I am on "/user/logout"
    And I go to the homepage
    Then I should be on "/user/login?destination=/home"
    And I should not see "Page not found"
