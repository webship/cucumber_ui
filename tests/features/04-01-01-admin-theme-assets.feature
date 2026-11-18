Feature: Cucumber UI attaches its scripts and its administration theme stylesheet
  As a site administrator
  I want the Cucumber UI pages to load their own CSS and scripts
  So that the runner and the editor are styled and behave as designed

  Cucumber UI brings a stylesheet for Claro and for Gin: the Claro scenarios
  run on a site whose administration theme is Claro (CI's Standard site), and
  a Cucumber site, which runs UIkit Admin, leaves them out (@claro).

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The run tests page loads HTMX
    When I navigate to "/admin/config/development/cucumber-ui"
    Then the page should load the asset "htmx.min.js"
     And the page should load the asset "htmx-behaviors.js"

  Scenario: The create test page loads HTMX and the editor
    When I navigate to "/admin/config/development/cucumber-ui/new"
    Then the page should load the asset "htmx.min.js"
     And the page should load the asset "cucumber-ui.ace-editor.js"

  @claro
  Scenario Outline: The <page> page loads the Claro stylesheet of Cucumber UI
    When I navigate to "<path>"
    Then the page should load the asset "cucumber-ui-style.claro.css"

    Examples:
      | page        | path                                           |
      | run tests   | /admin/config/development/cucumber-ui          |
      | create test | /admin/config/development/cucumber-ui/new      |
      | settings    | /admin/config/development/cucumber-ui/settings |
