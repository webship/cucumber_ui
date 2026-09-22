Feature: The Cucumber UI create test page
  As a tester
  I want to write a Gherkin feature in the Cucumber UI editor
  So that I can build and run a scenario without leaving the browser

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The create test page loads the Gherkin editor
    When I navigate to "/admin/config/development/cucumber-ui/new"
    Then I should see "Create test"
     And I should see "Feature"
     And "#edit-free-text" should be visible
     And "textarea.free-text-ace-editor" should be visible
     And the page should load the asset "cucumber-ui.ace-editor.js"

  Scenario: The create test page shows its action buttons
    When I navigate to "/admin/config/development/cucumber-ui/new"
    Then I should see the button "Run >>"
     And I should see the button "Save"
     And I should see the button "Download"

  Scenario: The Run and Save controls post the Gherkin script with HTMX
    When I navigate to "/admin/config/development/cucumber-ui/new"
    Then the element "#cucumber-ui-run" with the attribute "data-hx-post" and the value "/cucumber-ui/run" should exist
     And the element "#cucumber-ui-run" with the attribute "data-hx-include" and the value containing "free_text" should exist
     And the element "#cucumber-ui-save" with the attribute "data-hx-post" and the value "/cucumber-ui/save" should exist
     And the element "#cucumber-ui-save" with the attribute "data-hx-target" and the value "#cucumber-ui-save-output" should exist

  Scenario: Saving the Gherkin script reports back into the save region
    When I navigate to "/admin/config/development/cucumber-ui/new"
     And I click the "Save" button
    Then eventually I should see "Saved the testing feature to" within 30 seconds

  Scenario: The step definition lists are lazily loaded disclosures
    When I navigate to "/admin/config/development/cucumber-ui/new"
    Then I should see "Check available steps"
     And I should see "Full steps with info"
     And the element "#cucumber-ui-steps-list .cucumber-ui-steps__body" with the attribute "data-hx-trigger" and the value containing "toggle" should exist
