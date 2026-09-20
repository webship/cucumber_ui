Feature: Cucumber UI attaches its administration theme assets
  As a site administrator
  I want the Cucumber UI pages to load their own CSS and scripts
  So that the runner and the editor are styled and behave as designed

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The run tests page loads the Claro stylesheet and its script
    When I navigate to "/admin/config/development/cucumber-ui"
    Then the page should load the asset "cucumber-ui-style.claro.css"
     And the page should load the asset "cucumber-ui.run-tests.js"

  Scenario: The create test page loads the Claro stylesheet and its script
    When I navigate to "/admin/config/development/cucumber-ui/new"
    Then the page should load the asset "cucumber-ui-style.claro.css"
     And the page should load the asset "cucumber-ui.new-test.js"

  Scenario: The settings page loads the Claro stylesheet
    When I navigate to "/admin/config/development/cucumber-ui/settings"
    Then the page should load the asset "cucumber-ui-style.claro.css"
