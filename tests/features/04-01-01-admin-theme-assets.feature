Feature: Cucumber UI attaches its administration theme assets
  As a site administrator
  I want the Cucumber UI pages to load their own CSS and scripts
  So that the runner and the editor are styled and behave as designed

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The run tests page loads the Claro stylesheet and HTMX
    When I navigate to "/admin/config/development/cucumber-ui"
    Then the page should load the asset "cucumber-ui-style.claro.css"
     And the page should load the asset "htmx.min.js"
     And the page should load the asset "htmx-behaviors.js"

  Scenario: The create test page loads the Claro stylesheet, HTMX and the editor
    When I navigate to "/admin/config/development/cucumber-ui/new"
    Then the page should load the asset "cucumber-ui-style.claro.css"
     And the page should load the asset "htmx.min.js"
     And the page should load the asset "cucumber-ui.ace-editor.js"

  Scenario: The settings page loads the Claro stylesheet
    When I navigate to "/admin/config/development/cucumber-ui/settings"
    Then the page should load the asset "cucumber-ui-style.claro.css"
