@core_functionality
Feature: Test Learner views - Core Functionality

  @api @javascript
  Scenario: Verify bookmarked node shows up
    Given I am logged in with email address as a user with the "authenticated user" role
    When I am on "/discover"
    And I click the element ".o-flag--bookmark"
    And I wait 10 seconds
    Then I should see an ".o-flag--bookmark.is-active" element
    When I am on "/bookmarks"
    Then I should see an "article" element
