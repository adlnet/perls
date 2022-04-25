@core_functionality @search_api
Feature: Test regarding search

  Background:
    Given "category" terms:
      | name      |
      | eLearning |

    Given the following content:
      """
      title: Test article
      type: learn_article
      langcode: en
      field_topic: eLearning
      moderation_state: published
      field_body:
        -
          type: text
          field_paragraph_body: Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec et ex risus.
      """

  @api @javascript @test
  Scenario: Make sure search bar works
    Given I am logged in with email address as a user with the "authenticated" role
    And I am on "/bookmarks"
    And I click the element ".c-search-block__toggle"
    And I fill in "search_api_fulltext" with "Test article"
    And I click the element ".c-search-block .button"
    And I wait 10 seconds
    Then should see an ".c-node" element

  @api @javascript
  Scenario: Make sure that user see the proper message when the search result is empty.

    Given I am logged in with email address as a user with the "authenticated" role
    And I am on "/bookmarks"
    And I click the element ".c-search-block__toggle"
    And I fill in "search_api_fulltext" with "xyz"
    And I click the element ".c-search-block .button"
    And I wait 10 seconds
    Then should see "There are no results found!"

