@test @core_functionality
Feature: Test all core functionality of the site which doesn't belong to other category.

  Background:
    Given "category" terms:
      | name      |
      | eLearning |

    Given the following content:
      """
      title: Test article
      type: learn_article
      langcode: en
      moderation_state: published
      field_topic: eLearning
      field_body:
        -
          type: text
          field_paragraph_body: Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec et ex risus.
      """

  @api @javascript @search_api
  Scenario: Make sure that authenticated users can use the search page
    Given I am logged in with email address as a user with the "authenticated user" role
    And I am on "/search?text=Test article"
    Then the "body" element should contain "class=\"c-view__content\""
    Then I should not see "Undefined index"
