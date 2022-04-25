@core_functionality
Feature: Test Learner views

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

    Given users:
      | name                   | mail                   | roles    |
      | testlearner36@test.com | testlearner36@test.com |          |
      | testsysadmin@test.com  | testsysadmin@test.com  | sysadmin |

  @api @javascript
  Scenario: Verify bookmarking functionality, page display and filter
    Given I am logged in as the recently created user with the "authenticated" role
    When I am on "/bookmarks"
    Then I should see "You haven’t bookmarked anything yet"
    And I am at "/user/logout"
    Given I am logged in as "testsysadmin@test.com"
    And I am at "/admin/people"
    And I click the element ".username"
    And I click "State"
    And I click "Bookmarked"
    # Bookmark a known item
    When I select "- Any -" from "edit-flagged"
    And I fill in "edit-title" with "Test learn article"
    And I click the element "#edit-submit-administrate-user-flags"
    And I select all rows in the table
    And I select "Bookmark" from "edit-action"
    And I press "Apply to selected items"
    And I wait for the batch job to finish
    Then I should see "Action processing results: Bookmark added (1)"
    # Verify that the learner sees the bookmarked items
    And I am at "/user/logout"
    Given I am logged in as the recently created user with the "authenticated" role
    And I am on "/user"
    When I am on "/bookmarks"
    And the ".views-exposed-form" element should contain "form-item-title"
    When I fill in "edit-title" with "Test learn article"
    And press "Apply"
    Then the ".c-view__content" element should contain "Test learn article"
