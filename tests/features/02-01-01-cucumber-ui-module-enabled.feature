Feature: Cucumber UI module is enabled
  As a site administrator
  I want to verify that the Cucumber UI module is installed
  So that testers can create and run Cucumber tests from the web interface

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Modules page lists Cucumber UI as enabled
    When I navigate to "/admin/modules"
    Then I should see "cucumber UI"
     And I should see "Create and run cucumber/Mink tests from the web interface"
     And the element "#edit-modules-cucumber-ui-enable" with the attribute "checked" and the value "checked" should exist

  Scenario: Cucumber UI permissions are listed
    When I navigate to "/admin/people/permissions/module/cucumber_ui"
    Then I should see "Create tests with Cucumber UI"
     And I should see "Run all tests in Cucumber UI"
     And I should see "Administer Cucumber UI settings"
