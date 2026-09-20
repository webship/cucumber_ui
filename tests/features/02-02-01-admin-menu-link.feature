Feature: Cucumber UI administration menu link
  As a site administrator
  I want a Cucumber UI entry under Configuration > Development
  So that I can reach the test runner from the administration menu

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The Development section links to Cucumber UI
    When I navigate to "/admin/config"
    Then I should see "Development"
     And I should see "Cucumber UI"
     And I should see "Automated tests using Cucumber/Mink"
     And the link "Cucumber UI" with the href "/admin/config/development/cucumber-ui" should exist

  Scenario: The Development overview links to Cucumber UI
    When I navigate to "/admin/config/development"
    Then I should see "Cucumber UI"
     And the link "Cucumber UI" with the href "/admin/config/development/cucumber-ui" should exist
