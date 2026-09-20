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
