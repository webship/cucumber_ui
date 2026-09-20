Feature: The Cucumber UI settings page
  As a site administrator
  I want the Cucumber UI settings form with its shipped defaults
  So that I can point the module at the Cucumber configuration of the site

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The settings form lists every setting
    When I navigate to "/admin/config/development/cucumber-ui/settings"
    Then I should see "Cucumber configuration directory path"
     And I should see "Cucumber configuration file name"
     And I should see "Cucumber features directory path"
     And I should see "Enable HTML report format"
     And I should see "HTML report directory"
     And I should see "HTML report format"
     And I should see "Enable JSON report format"
     And I should see "JSON report directory"
     And I should see "Console log report directory"
     And I should see the button "Save configuration"

  Scenario: The settings form is filled with the shipped default values
    When I navigate to "/admin/config/development/cucumber-ui/settings"
    Then "#edit-config-path" should have value "."
     And "#edit-config-file" should have value "./nightwatch.conf.js"
     And "#edit-features-path" should have value "tests/features"
     And "#edit-html-report-dir" should have value "tests/reports"
     And "#edit-log-report-dir" should have value "tests/logs"
