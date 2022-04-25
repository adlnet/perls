@core_functionality
Feature: Test Learner Feedback form

  @api @javascript
  Scenario: Verify Feedback block is present for Content
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
    And the following content:
      """
      title: Test course
      type: course
      langcode: en
      field_topic: eLearning
      field_learning_content: Test learn article
      """
    And I am logged in with email address as a user with the "authenticated user" role
    # Navigate to Course
    When I am visiting the "course" content "Test course"
    # Feedback block should be visible, not broken and contain feedback form field
    Then I should see "Was this relevant to you?"
    And I should not see "This block is broken or missing"
    And I should see an ".relevant-content-rate" element
    And should see an ".js-form-item-feedback" element
    # Test the Form
    When I fill in "content_relevant" with "1"
    And I wait 2 seconds
    And I fill in "feedback" with "Test Feedback"
    And I press "Submit"
    And I wait 10 seconds
    Then should see an ".webform-confirmation" element
