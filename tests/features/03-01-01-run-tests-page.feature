Feature: The Cucumber UI run tests page
  As a tester
  I want the Cucumber UI page to load with its runner controls
  So that I can run the Cucumber tests of the site

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The run tests page loads with its local tasks
    When I navigate to "/admin/config/development/cucumber-ui"
    Then I should see "Cucumber UI"
     And I should see the local task "Run tests"
     And I should see the local task "Create test"
     And I should see the local task "Settings"

  Scenario: The run tests page shows the runner controls
    When I navigate to "/admin/config/development/cucumber-ui"
    Then I should see the button "Run cucumber tests"
     And I should see "Status:"
     And I should see "Not running"
     And "#cucumber-ui-status" should be visible

  Scenario: The status region polls the server with HTMX
    When I navigate to "/admin/config/development/cucumber-ui"
    Then the element "#cucumber-ui-status" with the attribute "data-hx-get" and the value "/cucumber-ui/status" should exist
     And the element "#cucumber-ui-status" with the attribute "data-hx-trigger" and the value "every 10s" should exist
     And the element "#cucumber-ui-status" with the attribute "data-hx-swap" and the value containing "outerHTML" should exist

  Scenario: The tests output region reloads itself when a run finishes
    When I navigate to "/admin/config/development/cucumber-ui"
    Then the element "#cucumber-ui-output" with the attribute "data-hx-get" and the value "/cucumber-ui/output" should exist
     And the element "#cucumber-ui-output" with the attribute "data-hx-trigger" and the value containing "cucumber-ui-tests-finished" should exist

  Scenario: The run tests page carries no jQuery behaviour of its own
    When I navigate to "/admin/config/development/cucumber-ui"
    Then the page should load the asset "htmx.min.js"
