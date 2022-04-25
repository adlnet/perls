@core_functionality
Feature: Test Learner menus

  @api @javascript
  Scenario: Verify Bookmarks menu exists under Me and redirects to the Bookmarks Page
    Given I am logged in with email address as a user with the "authenticated user" role
    When I click the element ".c-menu__item--me"
    Then I should see an ".c-menu__item--bookmarks" element
    # Redirects to Bookmarks page
    When I click the element ".c-menu__item--bookmarks"
    And I should see an ".l-page--bookmarks" element
