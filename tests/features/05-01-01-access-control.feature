Feature: Access control for the Cucumber UI pages
  As a site administrator
  I want the Cucumber UI pages to be protected by their permissions
  So that only privileged users can create and run tests

  Scenario: Anonymous user cannot reach the run tests page
    Given I am an anonymous user
    When I navigate to "/admin/config/development/cucumber-ui"
    Then I should see "Access denied"

  Scenario: Anonymous user cannot reach the create test page
    Given I am an anonymous user
    When I navigate to "/admin/config/development/cucumber-ui/new"
    Then I should see "Access denied"

  Scenario: Anonymous user cannot reach the settings page
    Given I am an anonymous user
    When I navigate to "/admin/config/development/cucumber-ui/settings"
    Then I should see "Access denied"

  Scenario: Authenticated user cannot reach the run tests page
    Given I am a logged in user with the "Authenticated user" user
    When I navigate to "/admin/config/development/cucumber-ui"
    Then I should see "Access denied"

  Scenario: Authenticated user cannot reach the settings page
    Given I am a logged in user with the "Authenticated user" user
    When I navigate to "/admin/config/development/cucumber-ui/settings"
    Then I should see "Access denied"
