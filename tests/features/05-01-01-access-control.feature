Feature: Access control for the Cucumber UI pages
  As a site administrator
  I want the Cucumber UI pages to be protected by their permissions
  So that only privileged users can create and run tests

  A refused request is a 403, the login form in place of the page (a site that
  sends visitors to the log in page), or the "Access denied" page.

  Scenario: Anonymous user cannot reach the run tests page
    Given I am an anonymous user
    Then I should be denied access to "/admin/config/development/cucumber-ui"

  Scenario: Anonymous user cannot reach the create test page
    Given I am an anonymous user
    Then I should be denied access to "/admin/config/development/cucumber-ui/new"

  Scenario: Anonymous user cannot reach the settings page
    Given I am an anonymous user
    Then I should be denied access to "/admin/config/development/cucumber-ui/settings"

  Scenario: Authenticated user cannot reach the run tests page
    Given I am a logged in user with the "Authenticated user" user
    Then I should be denied access to "/admin/config/development/cucumber-ui"

  Scenario: Authenticated user cannot reach the settings page
    Given I am a logged in user with the "Authenticated user" user
    Then I should be denied access to "/admin/config/development/cucumber-ui/settings"
