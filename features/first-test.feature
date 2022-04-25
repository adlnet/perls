@core_functionality
Feature: Test account menu links

@javascript @api
Scenario: Make sure that anonymous users see the account menu
  Given I am not logged in
  And I am on "/"
  Then I should see the link "Create Account"
