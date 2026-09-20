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
